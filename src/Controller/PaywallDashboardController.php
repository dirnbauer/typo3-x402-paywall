<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webconsulting\X402Paywall\Configuration\ConfigurationProvider;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Http\X402Header;
use Webconsulting\X402Paywall\Service\PaymentLogger;
use Webconsulting\X402Paywall\Service\ReportingPeriod;
use Webconsulting\X402Paywall\Utility\HttpUrl;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * Backend module "Web > x402 Paywall": revenue dashboard and request simulator.
 */
final readonly class PaywallDashboardController
{
    private const LANGUAGE_FILE = 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_mod.xlf:';
    private const PROBE_TIMEOUT = 10;

    /**
     * Simulator scenarios: id => [path below the site base, send a mock PAYMENT-SIGNATURE].
     *
     * @var array<string, array{0: string, 1: bool}>
     */
    private const SCENARIOS = [
        'plain_get' => ['/premium-content', false],
        'mock_signature' => ['/premium-content', true],
        'news_detail' => ['/news/detail?tx_news_pi1[news]=1&tx_news_pi1[action]=detail', false],
        'api_route' => ['/api/v1/content/42', false],
    ];

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private PaymentLogger $paymentLogger,
        private RequestFactory $requestFactory,
        private SiteFinder $siteFinder,
        private ConfigurationProvider $configurationProvider,
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $periods = [];
        foreach (ReportingPeriod::cases() as $period) {
            $periods[] = [
                'label' => self::LANGUAGE_FILE . $period->label(),
                'stats' => $this->paymentLogger->getStats($period->since()),
            ];
        }

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($this->translate('mlang_labels_tablabel'));
        $moduleTemplate->assignMultiple([
            'periods' => $periods,
            'topPages' => $this->paymentLogger->getTopPages(10, ReportingPeriod::Last30Days->since()),
            'recentTransactions' => $this->paymentLogger->getRecentTransactions(20),
        ]);

        return $moduleTemplate->renderResponse('Dashboard/Main');
    }

    public function simulatorAction(ServerRequestInterface $request): ResponseInterface
    {
        $site = $this->firstSite();
        $baseUrl = $site instanceof Site ? rtrim((string)$site->getBase(), '/') : 'https://your-typo3.local';
        $config = $site instanceof Site ? $this->configurationProvider->getForSite($site) : new PaywallConfiguration();

        $scenarios = [];
        foreach (self::SCENARIOS as $id => [$path, $mockSignature]) {
            $scenarios[] = $this->scenario($id, $baseUrl . $path, $mockSignature);
        }
        $scenarios[] = $this->scenario('facilitator_check', rtrim($config->facilitatorUrl, '/') . '/supported', false);

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($this->translate('simulator.title'));
        $moduleTemplate->assign('scenarios', $scenarios);

        return $moduleTemplate->renderResponse('Dashboard/Simulator');
    }

    /**
     * AJAX: GET a public URL and report status, headers, body and the decoded PAYMENT-REQUIRED header.
     * With signature=mock a 402 is answered like a client would: a syntactically valid PaymentPayload for
     * the offered requirement with a dummy signature, which the facilitator must reject.
     */
    public function runSimulationAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = Json::decodeObject((string)$request->getBody());
        } catch (\JsonException) {
            return $this->jsonError('simulator.error.invalid_json');
        }

        $url = ScalarValue::string($body['url'] ?? null);
        if ($url === '') {
            return $this->jsonError('simulator.error.missing_url');
        }
        if (!HttpUrl::isAllowedOutboundHttpUrl($url)) {
            return $this->jsonError('simulator.error.disallowed_url');
        }

        $headers = [
            'Accept' => 'application/json, text/html, */*',
            'User-Agent' => 'x402-simulator/TYPO3-backend',
        ];

        try {
            $response = $this->requestFactory->request($url, 'GET', $this->requestOptions($headers));
            $offered = $this->decodePaymentRequired($response->getHeaderLine(X402Header::PAYMENT_REQUIRED));

            if (ScalarValue::string($body['signature'] ?? null) === 'mock' && $offered instanceof PaymentRequired) {
                $headers[X402Header::PAYMENT_SIGNATURE] = self::mockPaymentSignature($offered);
                $response = $this->requestFactory->request($url, 'GET', $this->requestOptions($headers));
                $offered = $this->decodePaymentRequired($response->getHeaderLine(X402Header::PAYMENT_REQUIRED)) ?? $offered;
            }
        } catch (\Throwable $exception) {
            return new JsonResponse(['error' => $exception->getMessage()]);
        }

        return new JsonResponse([
            'status' => $response->getStatusCode(),
            'headers' => array_map(static fn(array $values): string => implode(', ', $values), $response->getHeaders()),
            'body' => substr((string)$response->getBody(), 0, 2000),
            'decodedRequirement' => $offered?->toArray(),
        ]);
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function requestOptions(array $headers): array
    {
        return ['headers' => $headers, 'timeout' => self::PROBE_TIMEOUT, 'allow_redirects' => false, 'http_errors' => false];
    }

    private function decodePaymentRequired(string $headerValue): ?PaymentRequired
    {
        if ($headerValue === '') {
            return null;
        }

        try {
            return PaymentRequired::fromHeaderValue($headerValue);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * x402 v2 PaymentPayload (exact/EVM, EIP-3009) for the first offered requirement with a dummy signature.
     */
    private static function mockPaymentSignature(PaymentRequired $offered): string
    {
        $requirement = $offered->first();
        $now = time();

        return base64_encode(Json::encode([
            'x402Version' => PaymentRequired::X402_VERSION,
            'resource' => $offered->resource->toArray(),
            'accepted' => $requirement->toArray(),
            'payload' => [
                'signature' => '0x' . str_repeat('ab', 65),
                'authorization' => [
                    'from' => '0x0000000000000000000000000000000000000001',
                    'to' => $requirement->payTo,
                    'value' => $requirement->amount,
                    'validAfter' => (string)($now - 60),
                    'validBefore' => (string)($now + $requirement->maxTimeoutSeconds),
                    'nonce' => '0x' . str_repeat('00', 32),
                ],
            ],
        ]));
    }

    /**
     * @return array{id: string, label: string, url: string, signature: string, description: string}
     */
    private function scenario(string $id, string $url, bool $mockSignature): array
    {
        return [
            'id' => $id,
            'label' => $this->translate('simulator.scenario.' . $id . '.label'),
            'url' => $url,
            'signature' => $mockSignature ? 'mock' : '',
            'description' => $this->translate('simulator.scenario.' . $id . '.description'),
        ];
    }

    private function firstSite(): ?Site
    {
        $sites = $this->siteFinder->getAllSites();
        $site = reset($sites);

        return $site instanceof Site ? $site : null;
    }

    private function jsonError(string $labelKey): JsonResponse
    {
        return new JsonResponse(['error' => $this->translate($labelKey)], 400);
    }

    private function translate(string $key): string
    {
        $label = $this->languageServiceFactory->createFromUserPreferences($GLOBALS['BE_USER'] ?? null)->sL(self::LANGUAGE_FILE . $key);

        return $label !== '' ? $label : $key;
    }
}
