<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Http;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Domain\Model\SettlementResponse;
use Webconsulting\X402Paywall\Legacy\X402V1;
use Webconsulting\X402Paywall\Utility\Json;

/**
 * Builds "402 Payment Required" responses: the PaymentRequired document travels base64-encoded in the
 * PAYMENT-REQUIRED header; the body is implementation-specific per the HTTP transport specification.
 * Browsers (Accept: text/html) receive the wallet paywall page, every other client a JSON copy of the
 * document (the v1 "PaymentRequirementsResponse" shape when legacy_v1 is enabled).
 *
 * A payment that verified but did not settle is answered with a 402 carrying the SettlementResponse
 * in PAYMENT-RESPONSE instead (HTTP transport v2, "Settlement Response Delivery").
 */
final readonly class PaymentRequiredResponseFactory
{
    private const string TEMPLATE_ROOT = 'EXT:x402_paywall/Resources/Private/Templates/Paywall';

    public function __construct(
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private ViewFactoryInterface $viewFactory,
    ) {}

    public function isBrowserRequest(ServerRequestInterface $request): bool
    {
        return str_contains(strtolower($request->getHeaderLine('Accept')), 'text/html');
    }

    public function create(
        ServerRequestInterface $request,
        PaymentRequired $paymentRequired,
        PaywallConfiguration $config,
        ?string $error = null,
    ): ResponseInterface {
        if ($error !== null) {
            $paymentRequired = $paymentRequired->withError($error);
        }

        $response = $this->responseFactory->createResponse(402, 'Payment Required')
            ->withHeader(X402Header::PAYMENT_REQUIRED, $paymentRequired->toHeaderValue())
            ->withHeader('Cache-Control', 'no-store');

        if ($this->isBrowserRequest($request)) {
            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withBody($this->streamFactory->createStream($this->renderPaywall($request, $paymentRequired, $config)));
        }

        $body = $config->legacyV1
            ? X402V1::paymentRequiredToArray($paymentRequired, $config->getLegacyNetworkId())
            : $paymentRequired->toArray();

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(Json::encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
    }

    /**
     * 402 for a verified payment that did not settle. x402 v2 clients get the SettlementResponse in
     * PAYMENT-RESPONSE and a JSON copy as body; x402 v1 clients the v1 402 body with the error reason,
     * as before, plus X-PAYMENT-RESPONSE.
     */
    public function createSettlementFailure(
        ServerRequestInterface $request,
        PaymentRequired $paymentRequired,
        PaywallConfiguration $config,
        SettlementResponse $settlement,
        bool $legacy,
    ): ResponseInterface {
        if ($legacy) {
            return $this->create($request, $paymentRequired, $config, $settlement->errorReason)
                ->withHeader(X402V1::HEADER_PAYMENT_RESPONSE, $settlement->toHeaderValue());
        }

        return $this->responseFactory->createResponse(402, 'Payment Required')
            ->withHeader(X402Header::PAYMENT_RESPONSE, $settlement->toHeaderValue())
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(Json::encode($settlement->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
    }

    private function renderPaywall(ServerRequestInterface $request, PaymentRequired $paymentRequired, PaywallConfiguration $config): string
    {
        $requirement = $paymentRequired->first();
        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: [self::TEMPLATE_ROOT],
            request: $request,
        ));
        $view->assignMultiple([
            'paymentRequired' => Json::encode($paymentRequired->toArray(), JSON_UNESCAPED_SLASHES),
            'price' => PaymentRequirement::fromAtomicUnits($requirement->amount, $config->assetDecimals),
            'currency' => $config->currency,
            'description' => $paymentRequired->resource->description,
            'resourceUrl' => $paymentRequired->resource->url,
            'network' => $requirement->network,
            'networkLabel' => $config->getNetworkLabel(),
            'error' => $paymentRequired->error,
        ]);

        return $view->render('PaymentRequired');
    }
}
