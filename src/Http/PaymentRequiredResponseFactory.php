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
use Webconsulting\X402Paywall\Utility\Json;

/**
 * Builds "402 Payment Required" responses following the x402 v2 HTTP transport:
 * the PaymentRequired document travels base64-encoded in the PAYMENT-REQUIRED header,
 * the body is implementation-specific. Browsers (Accept: text/html) receive a wallet paywall
 * page, every other client a JSON copy of the PaymentRequired document.
 */
final class PaymentRequiredResponseFactory
{
    public const HEADER_PAYMENT_REQUIRED = 'PAYMENT-REQUIRED';

    private const TEMPLATE_ROOT = 'EXT:x402_paywall/Resources/Private/Templates/Paywall';

    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
        private readonly ViewFactoryInterface $viewFactory,
    ) {}

    public function isBrowserRequest(ServerRequestInterface $request): bool
    {
        $accept = strtolower($request->getHeaderLine('Accept'));

        return str_contains($accept, 'text/html');
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
            ->withHeader(self::HEADER_PAYMENT_REQUIRED, $paymentRequired->toHeaderValue())
            ->withHeader('Cache-Control', 'no-store');

        if ($this->isBrowserRequest($request)) {
            return $response
                ->withHeader('Content-Type', 'text/html; charset=utf-8')
                ->withBody($this->streamFactory->createStream($this->renderPaywall($request, $paymentRequired, $config)));
        }

        $body = $config->legacyV1
            ? $paymentRequired->toLegacyArray($config->getLegacyNetworkId())
            : $paymentRequired->toArray();

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->streamFactory->createStream(Json::encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)));
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
            'paymentRequiredBase64' => $paymentRequired->toHeaderValue(),
            'price' => PaymentRequirement::fromAtomicUnits($requirement->amount, $config->assetDecimals),
            'currency' => $config->currency,
            'description' => $paymentRequired->resource->description,
            'resourceUrl' => $paymentRequired->resource->url,
            'network' => $requirement->network,
            'networkLabel' => $config->getNetworkLabel(),
            'chainId' => $config->getChainId(),
            'error' => $paymentRequired->error,
        ]);

        return $view->render('PaymentRequired');
    }
}
