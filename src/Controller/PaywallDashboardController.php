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
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Http\PaymentRequiredResponseFactory;
use Webconsulting\X402Paywall\Middleware\X402PaywallMiddleware;
use Webconsulting\X402Paywall\Service\PaymentLogger;
use Webconsulting\X402Paywall\Utility\HttpUrl;
use Webconsulting\X402Paywall\Utility\Json;

/**
 * Backend module controller for x402 payment dashboard.
 */
final readonly class PaywallDashboardController
{
    private const LANGUAGE_FILE = 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_mod.xlf:';

    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private PaymentLogger $paymentLogger,
        private RequestFactory $requestFactory,
        private SiteFinder $siteFinder,
    ) {}

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $thirtyDaysAgo = $this->timestamp('-30 days');
        $sevenDaysAgo = $this->timestamp('-7 days');
        $today = $this->timestamp('today');

        $stats30d = $this->paymentLogger->getStats($thirtyDaysAgo);
        $stats7d = $this->paymentLogger->getStats($sevenDaysAgo);
        $statsToday = $this->paymentLogger->getStats($today);
        $statsAll = $this->paymentLogger->getStats();
        $topPages = $this->paymentLogger->getTopPages(10, $thirtyDaysAgo);
        $recentTx = $this->paymentLogger->getRecentTransactions(20);

        $moduleTemplate->assignMultiple([
            'stats30d' => $stats30d,
            'stats7d' => $stats7d,
            'statsToday' => $statsToday,
            'statsAll' => $statsAll,
            'topPages' => $topPages,
            'recentTransactions' => $recentTx,
        ]);

        $moduleTemplate->setTitle($this->translate('mlang_labels_tablabel'));

        return $moduleTemplate->renderResponse('Dashboard/Main');
    }

    public function simulatorAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle($this->translate('simulator.title'));

        // Collect site base URLs for pre-filled scenarios
        $siteBaseUrls = [];
        try {
            foreach ($this->siteFinder->getAllSites() as $site) {
                $siteBaseUrls[] = rtrim((string)$site->getBase(), '/');
            }
        } catch (\Exception) {
            // no sites configured yet
        }
        $baseUrl = $siteBaseUrls[0] ?? 'https://your-typo3.local';

        $scenarios = [
            [
                'id' => 'plain_get',
                'label' => $this->translate('simulator.scenario.plain_get.label'),
                'url' => $baseUrl . '/premium-content',
                'signature' => '',
                'description' => $this->translate('simulator.scenario.plain_get.description'),
            ],
            [
                'id' => 'mock_signature',
                'label' => $this->translate('simulator.scenario.mock_signature.label'),
                'url' => $baseUrl . '/premium-content',
                'signature' => 'mock',
                'description' => $this->translate('simulator.scenario.mock_signature.description'),
            ],
            [
                'id' => 'news_detail',
                'label' => $this->translate('simulator.scenario.news_detail.label'),
                'url' => $baseUrl . '/news/detail?tx_news_pi1[news]=1&tx_news_pi1[action]=detail',
                'signature' => '',
                'description' => $this->translate('simulator.scenario.news_detail.description'),
            ],
            [
                'id' => 'api_route',
                'label' => $this->translate('simulator.scenario.api_route.label'),
                'url' => $baseUrl . '/api/v1/content/42',
                'signature' => '',
                'description' => $this->translate('simulator.scenario.api_route.description'),
            ],
            [
                'id' => 'facilitator_check',
                'label' => $this->translate('simulator.scenario.facilitator_check.label'),
                'url' => 'https://x402.org/facilitator/supported',
                'signature' => '',
                'description' => $this->translate('simulator.scenario.facilitator_check.description'),
            ],
        ];

        $moduleTemplate->assignMultiple([
            'scenarios' => $scenarios,
            'defaultBaseUrl' => $baseUrl,
        ]);

        return $moduleTemplate->renderResponse('Dashboard/Simulator');
    }

    /**
     * AJAX: execute a simulated HTTP request and return structured result.
     */
    public function runSimulationAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = Json::decodeObject((string)$request->getBody());
        } catch (\JsonException) {
            return $this->jsonError('simulator.error.invalid_json', 400);
        }

        $url = $this->nonEmptyString($body['url'] ?? null);
        $signatureMode = $this->nonEmptyString($body['signature'] ?? null);

        if ($url === '') {
            return $this->jsonError('simulator.error.missing_url', 400);
        }

        $headers = [
            'Accept' => 'application/json, text/html, */*',
            'User-Agent' => 'x402-simulator/TYPO3-backend',
        ];

        if ($signatureMode === 'mock') {
            // Syntactically valid x402 v2 PaymentPayload (exact/EVM, EIP-3009) with a dummy signature:
            // the facilitator must reject it, which proves the verification path is wired.
            $headers[X402PaywallMiddleware::HEADER_PAYMENT_SIGNATURE] = base64_encode(Json::encode([
                'x402Version' => PaymentRequired::X402_VERSION,
                'accepted' => [
                    'scheme' => 'exact',
                    'network' => 'eip155:84532',
                    'amount' => '10000',
                    'asset' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
                    'payTo' => '0x0000000000000000000000000000000000000002',
                    'maxTimeoutSeconds' => 300,
                ],
                'payload' => [
                    'signature' => '0x' . str_repeat('ab', 65),
                    'authorization' => [
                        'from' => '0x0000000000000000000000000000000000000001',
                        'to' => '0x0000000000000000000000000000000000000002',
                        'value' => '10000',
                        'validAfter' => (string)(time() - 60),
                        'validBefore' => (string)(time() + 300),
                        'nonce' => '0x' . str_repeat('00', 32),
                    ],
                ],
            ]));
        }

        $steps = [];
        $startTime = microtime(true);

        try {
            $steps[] = ['type' => 'send', 'message' => 'GET ' . $url, 'ms' => 0];

            if (!HttpUrl::isAllowedOutboundHttpUrl($url)) {
                return $this->jsonError('simulator.error.disallowed_url', 400);
            }

            $response = $this->requestFactory->request($url, 'GET', [
                'headers' => $headers,
                'timeout' => 10,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);

            $elapsed = (int)((microtime(true) - $startTime) * 1000);
            $statusCode = $response->getStatusCode();
            $responseHeaders = [];
            foreach ($response->getHeaders() as $name => $values) {
                $responseHeaders[$name] = implode(', ', $values);
            }
            $responseBody = (string)$response->getBody();

            $steps[] = ['type' => 'receive', 'message' => "<- {$statusCode} (" . $elapsed . 'ms)', 'ms' => $elapsed];

            $decodedRequirement = null;
            $paymentHeader = $response->getHeaderLine(PaymentRequiredResponseFactory::HEADER_PAYMENT_REQUIRED);
            if ($paymentHeader !== '') {
                try {
                    $decodedRequirement = PaymentRequired::fromHeaderValue($paymentHeader)->toArray();
                    $steps[] = ['type' => 'info', 'message' => 'PAYMENT-REQUIRED decoded (x402 v2)', 'ms' => $elapsed];
                } catch (\InvalidArgumentException $exception) {
                    $steps[] = ['type' => 'error', 'message' => 'PAYMENT-REQUIRED header invalid: ' . $exception->getMessage(), 'ms' => $elapsed];
                }
            }

            if ($signatureMode === 'mock' && $statusCode === 402) {
                $steps[] = ['type' => 'facilitator', 'message' => '-> PAYMENT-SIGNATURE forwarded to the facilitator (/verify)', 'ms' => $elapsed + 5];
                $steps[] = ['type' => 'reject', 'message' => 'x Facilitator rejected the mock signature (expected)', 'ms' => $elapsed + 80];
            }

            return new JsonResponse([
                'status' => $statusCode,
                'headers' => $responseHeaders,
                'body' => substr($responseBody, 0, 2000),
                'decodedRequirement' => $decodedRequirement,
                'paymentHeader' => $paymentHeader,
                'steps' => $steps,
                'elapsed' => $elapsed,
                'signatureMode' => $signatureMode,
            ]);
        } catch (\Throwable $e) {
            $elapsed = (int)((microtime(true) - $startTime) * 1000);
            $steps[] = ['type' => 'error', 'message' => 'x ' . $e->getMessage(), 'ms' => $elapsed];

            return new JsonResponse([
                'status' => 0,
                'error' => $e->getMessage(),
                'steps' => $steps,
                'elapsed' => $elapsed,
            ]);
        }
    }

    /**
     * AJAX endpoint for real-time stats.
     */
    public function statsAction(ServerRequestInterface $request): ResponseInterface
    {
        $period = $request->getQueryParams()['period'] ?? '30days';

        $since = match ($period) {
            'today' => $this->timestamp('today'),
            '7days' => $this->timestamp('-7 days'),
            '30days' => $this->timestamp('-30 days'),
            default => 0,
        };

        return new JsonResponse([
            'stats' => $this->paymentLogger->getStats($since),
            'topPages' => $this->paymentLogger->getTopPages(10, $since),
            'recentTransactions' => $this->paymentLogger->getRecentTransactions(10),
        ]);
    }

    private function jsonError(string $labelKey, int $statusCode): JsonResponse
    {
        return new JsonResponse(['error' => $this->translate($labelKey)], $statusCode);
    }

    private function timestamp(string $modifier): int
    {
        $timestamp = strtotime($modifier);

        return $timestamp === false ? 0 : $timestamp;
    }

    private function nonEmptyString(mixed $value, string $default = ''): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $stringValue = trim((string)$value);

        return $stringValue !== '' ? $stringValue : $default;
    }

    private function translate(string $key): string
    {
        $languageService = $GLOBALS['LANG'] ?? null;
        if ($languageService instanceof LanguageService) {
            $label = $languageService->sL(self::LANGUAGE_FILE . $key);
            if ($label !== '') {
                return $label;
            }
        }

        return $key;
    }
}
