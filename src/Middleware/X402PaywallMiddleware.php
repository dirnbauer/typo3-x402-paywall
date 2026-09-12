<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Middleware;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Webconsulting\X402Paywall\Configuration\ConfigurationProvider;
use Webconsulting\X402Paywall\Domain\Model\PaymentPayload;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Domain\Model\ResourceInfo;
use Webconsulting\X402Paywall\Event\PaymentReceivedEvent;
use Webconsulting\X402Paywall\Event\PaymentRequiredEvent;
use Webconsulting\X402Paywall\Http\PaymentRequiredResponseFactory;
use Webconsulting\X402Paywall\Service\ContentTypeResolver;
use Webconsulting\X402Paywall\Service\PaymentLogger;
use Webconsulting\X402Paywall\Service\PaymentVerifier;
use Webconsulting\X402Paywall\Service\RequestAttributeResolver;
use Webconsulting\X402Paywall\Service\RouteGateResolver;
use Webconsulting\X402Paywall\Utility\Json;

/**
 * PSR-15 middleware implementing the x402 v2 HTTP transport (specification v2.0, 2025-12-09).
 *
 * 1. Gated resource without PAYMENT-SIGNATURE  -> 402 + PAYMENT-REQUIRED header (base64 PaymentRequired)
 * 2. PAYMENT-SIGNATURE present                  -> decode PaymentPayload, POST /verify at the facilitator
 * 3. Valid payment                              -> run the request, POST /settle, add PAYMENT-RESPONSE header
 *
 * With legacy_v1 enabled the middleware additionally understands the v1 X-PAYMENT request header, returns
 * the v1 JSON body and mirrors the settlement into X-PAYMENT-RESPONSE.
 */
final class X402PaywallMiddleware implements MiddlewareInterface
{
    public const HEADER_PAYMENT_SIGNATURE = 'PAYMENT-SIGNATURE';
    public const HEADER_PAYMENT_RESPONSE = 'PAYMENT-RESPONSE';
    public const HEADER_LEGACY_PAYMENT = 'X-PAYMENT';
    public const HEADER_LEGACY_PAYMENT_RESPONSE = 'X-PAYMENT-RESPONSE';

    public function __construct(
        private readonly ConfigurationProvider $configProvider,
        private readonly RouteGateResolver $gateResolver,
        private readonly PaymentVerifier $verifier,
        private readonly PaymentLogger $paymentLogger,
        private readonly ContentTypeResolver $contentTypeResolver,
        private readonly RequestAttributeResolver $requestAttributeResolver,
        private readonly PaymentRequiredResponseFactory $responseFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $config = $this->configProvider->getFromRequest($request);

        if (!$config->isValid() || !$this->gateResolver->isGated($request, $config)) {
            return $handler->handle($request);
        }

        $price = $this->gateResolver->getPrice($request, $config);
        $requestUri = (string)$request->getUri();
        $paymentRequired = new PaymentRequired(
            resource: new ResourceInfo(
                url: $requestUri,
                description: $this->gateResolver->getContentDescription($request),
                mimeType: $this->responseFactory->isBrowserRequest($request) ? 'text/html' : 'application/json',
            ),
            accepts: [PaymentRequirement::fromConfig($config, $price)],
        );

        $headerValue = $request->getHeaderLine(self::HEADER_PAYMENT_SIGNATURE);
        $legacyTransport = false;
        if ($headerValue === '' && $config->legacyV1) {
            $headerValue = $request->getHeaderLine(self::HEADER_LEGACY_PAYMENT);
            $legacyTransport = $headerValue !== '';
        }

        if ($headerValue === '') {
            $this->eventDispatcher->dispatch(new PaymentRequiredEvent(
                requestUri: $requestUri,
                price: $price,
                currency: $config->currency,
                network: $config->getCaip2NetworkId(),
            ));
            $this->logger->debug('x402: payment required for {uri}', ['uri' => $requestUri]);

            return $this->responseFactory->create($request, $paymentRequired, $config);
        }

        try {
            $payload = PaymentPayload::fromHeaderValue($headerValue);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->notice('x402: undecodable payment header for {uri}: {error}', ['uri' => $requestUri, 'error' => $exception->getMessage()]);

            return $this->responseFactory->create($request, $paymentRequired, $config, 'invalid_payload');
        }

        if ($payload->x402Version !== PaymentRequired::X402_VERSION && !($payload->isLegacy() && $config->legacyV1)) {
            return $this->responseFactory->create($request, $paymentRequired, $config, 'invalid_x402_version');
        }

        $requirement = $paymentRequired->first();
        if ($payload->isLegacy()) {
            $requirements = $requirement->toLegacyArray($paymentRequired->resource, $config->getLegacyNetworkId());
            $matches = $payload->getScheme() === $requirement->scheme && $payload->getNetwork() === $config->getLegacyNetworkId();
        } else {
            $requirements = $requirement->toArray();
            $matches = $requirement->matches($payload->accepted);
        }

        if (!$matches) {
            $this->logger->notice('x402: payment payload does not match the requirement for {uri}', ['uri' => $requestUri]);

            return $this->responseFactory->create($request, $paymentRequired, $config, 'invalid_payment_requirements');
        }

        $verification = $this->verifier->verify($payload, $requirements, $config);
        if (!$verification['valid']) {
            $this->logger->warning('x402: payment verification failed for {uri}: {error}', ['uri' => $requestUri, 'error' => $verification['error']]);

            return $this->responseFactory->create($request, $paymentRequired, $config, $verification['error']);
        }

        // Produce the resource first so that failed requests are never charged.
        $response = $handler->handle($request);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }

        $settlement = $this->verifier->settle($payload, $requirements, $config);
        $payer = $settlement['payer'] !== '' ? $settlement['payer'] : ($verification['payer'] !== '' ? $verification['payer'] : $payload->getPayer());

        $pageUid = $this->requestAttributeResolver->getPageUid($request);
        $contentInfo = $this->contentTypeResolver->resolve($request, $pageUid);
        $this->paymentLogger->logPayment(
            request: $request,
            pageUid: $pageUid,
            amount: $price,
            currency: $config->currency,
            network: $settlement['network'],
            txHash: $settlement['transaction'],
            status: $settlement['success'] ? PaymentLogger::STATUS_SETTLED : PaymentLogger::STATUS_FAILED,
            settlementDetails: $settlement['response'],
            contentType: $contentInfo['type'],
            contentUid: $contentInfo['uid'],
            payerAddress: $payer,
        );

        if (!$settlement['success']) {
            $this->logger->warning('x402: settlement failed for {uri}: {error}', ['uri' => $requestUri, 'error' => $settlement['errorReason']]);

            return $this->responseFactory->create($request, $paymentRequired, $config, $settlement['errorReason']);
        }

        $this->eventDispatcher->dispatch(new PaymentReceivedEvent(
            requestUri: $requestUri,
            price: $price,
            currency: $config->currency,
            txHash: $settlement['transaction'],
            network: $settlement['network'],
            payer: $payer,
        ));
        $this->logger->info('x402: payment received for {uri}', [
            'uri' => $requestUri,
            'price' => $price,
            'transaction' => $settlement['transaction'],
        ]);

        $settlementResponse = [
            'success' => true,
            'transaction' => $settlement['transaction'],
            'network' => $settlement['network'],
        ];
        if ($payer !== '') {
            $settlementResponse['payer'] = $payer;
        }
        if ($settlement['amount'] !== '') {
            $settlementResponse['amount'] = $settlement['amount'];
        }
        $encoded = base64_encode(Json::encode($settlementResponse));

        $response = $response->withHeader(self::HEADER_PAYMENT_RESPONSE, $encoded);
        if ($legacyTransport) {
            $response = $response->withHeader(self::HEADER_LEGACY_PAYMENT_RESPONSE, $encoded);
        }

        return $response->withAddedHeader('Cache-Control', 'no-store');
    }
}
