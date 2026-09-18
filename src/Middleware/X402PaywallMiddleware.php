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
use Webconsulting\X402Paywall\Http\X402Header;
use Webconsulting\X402Paywall\Legacy\X402V1;
use Webconsulting\X402Paywall\Service\ContentTypeResolver;
use Webconsulting\X402Paywall\Service\PaymentLogger;
use Webconsulting\X402Paywall\Service\PaymentVerifier;
use Webconsulting\X402Paywall\Service\RouteGateResolver;

/**
 * PSR-15 middleware implementing the x402 v2 HTTP transport (specification v2.0, 2025-12-09).
 *
 * 1. Gated resource without PAYMENT-SIGNATURE -> 402 + PAYMENT-REQUIRED header (base64 PaymentRequired)
 * 2. PAYMENT-SIGNATURE present                 -> decode PaymentPayload, match it, POST /verify
 * 3. Valid payment                             -> run the request, POST /settle, add PAYMENT-RESPONSE
 *
 * With legacy_v1 the X-PAYMENT request header and v1 payloads are accepted as well (see X402V1).
 */
final class X402PaywallMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly ConfigurationProvider $configProvider,
        private readonly RouteGateResolver $gateResolver,
        private readonly PaymentVerifier $verifier,
        private readonly PaymentLogger $paymentLogger,
        private readonly ContentTypeResolver $contentTypeResolver,
        private readonly PaymentRequiredResponseFactory $responseFactory,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $config = $this->configProvider->getFromRequest($request);
        if (!$this->gateResolver->isGated($request, $config)) {
            return $handler->handle($request);
        }

        $price = $this->gateResolver->getPrice($request, $config);
        $requestUri = (string)$request->getUri();
        $requirement = PaymentRequirement::fromConfig($config, $price);
        $paymentRequired = new PaymentRequired(
            resource: new ResourceInfo(
                url: $requestUri,
                description: $this->gateResolver->getContentDescription($request),
                mimeType: $this->responseFactory->isBrowserRequest($request) ? 'text/html' : 'application/json',
            ),
            accepts: [$requirement],
        );

        $headerValue = $request->getHeaderLine(X402Header::PAYMENT_SIGNATURE);
        if ($headerValue === '' && $config->legacyV1) {
            $headerValue = $request->getHeaderLine(X402V1::HEADER_PAYMENT);
        }
        if ($headerValue === '') {
            $this->eventDispatcher->dispatch(new PaymentRequiredEvent($requestUri, $price, $config->currency, $requirement->network));
            $this->logger->debug('x402: payment required for {uri}', ['uri' => $requestUri]);

            return $this->responseFactory->create($request, $paymentRequired, $config);
        }

        try {
            $payload = PaymentPayload::fromHeaderValue($headerValue);
        } catch (\InvalidArgumentException $exception) {
            $this->logger->notice('x402: undecodable payment header for {uri}: {error}', ['uri' => $requestUri, 'error' => $exception->getMessage()]);

            return $this->responseFactory->create($request, $paymentRequired, $config, 'invalid_payload');
        }

        $legacy = $payload->x402Version === X402V1::VERSION;
        if ($payload->x402Version !== PaymentRequired::X402_VERSION && !($legacy && $config->legacyV1)) {
            return $this->responseFactory->create($request, $paymentRequired, $config, 'invalid_x402_version');
        }

        // The offered requirement in the client's dialect: forwarded to the facilitator and matched against the payload.
        $offered = $legacy
            ? X402V1::requirementToArray($requirement, $paymentRequired->resource, $config->getLegacyNetworkId())
            : $requirement->toArray();
        $matches = $legacy
            ? X402V1::payloadMatches($payload, $requirement, $config->getLegacyNetworkId())
            : $requirement->matches($payload->accepted);
        if (!$matches) {
            $this->logger->notice('x402: payment payload does not match the requirement for {uri}', ['uri' => $requestUri]);

            return $this->responseFactory->create($request, $paymentRequired, $config, 'invalid_payment_requirements');
        }

        $verification = $this->verifier->verify($payload, $offered, $config);
        if (!$verification->isValid) {
            $this->logger->warning('x402: payment verification failed for {uri}: {error}', ['uri' => $requestUri, 'error' => $verification->invalidReason]);

            return $this->responseFactory->create($request, $paymentRequired, $config, $verification->invalidReason);
        }

        // Produce the resource first so that failed requests are never charged.
        $response = $handler->handle($request);
        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            return $response;
        }

        $settlement = $this->verifier->settle($payload, $offered, $config);
        $pageUid = $this->gateResolver->getPageUid($request);
        $content = $this->contentTypeResolver->resolve($request, $pageUid);
        $this->paymentLogger->logPayment($request, $pageUid, $content['type'], $content['uid'], $price, $config->currency, $settlement);

        if (!$settlement->success) {
            $this->logger->warning('x402: settlement failed for {uri}: {error}', ['uri' => $requestUri, 'error' => $settlement->errorReason]);

            return $this->responseFactory->create($request, $paymentRequired, $config, $settlement->errorReason);
        }

        $this->eventDispatcher->dispatch(new PaymentReceivedEvent(
            requestUri: $requestUri,
            price: $price,
            currency: $config->currency,
            txHash: $settlement->transaction,
            network: $settlement->network,
            payer: $settlement->payer,
        ));
        $this->logger->info('x402: payment received for {uri}', ['uri' => $requestUri, 'price' => $price, 'transaction' => $settlement->transaction]);

        $response = $response->withHeader(X402Header::PAYMENT_RESPONSE, $settlement->toHeaderValue());
        if ($legacy) {
            $response = $response->withHeader(X402V1::HEADER_PAYMENT_RESPONSE, $settlement->toHeaderValue());
        }

        return $response->withAddedHeader('Cache-Control', 'no-store');
    }
}
