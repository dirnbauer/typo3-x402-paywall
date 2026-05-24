<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Site\SiteFinder;
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
                'url' => 'https://x402.org/facilitator',
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
            $mockPayload = base64_encode(Json::encode([
                'from' => '0x0000000000000000000000000000000000000001',
                'signature' => '0x' . str_repeat('ab', 65),
                'network' => 'eip155:84532',
            ]));
            $headers['PAYMENT-SIGNATURE'] = $mockPayload;
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

            $steps[] = ['type' => 'receive', 'message' => "← {$statusCode} (" . $elapsed . 'ms)', 'ms' => $elapsed];

            $decodedRequirement = null;
            $paymentHeader = $response->getHeaderLine('PAYMENT-REQUIRED');
            if ($paymentHeader === '') {
                $paymentHeader = $response->getHeaderLine('X-PAYMENT-REQUIRED');
            }

            if ($paymentHeader !== '') {
                $decoded = base64_decode($paymentHeader, true);
                if ($decoded !== false) {
                    $decodedRequirement = Json::decodeObject($decoded);
                    $steps[] = ['type' => 'info', 'message' => '📋 Payment requirement decoded', 'ms' => $elapsed];
                }
            }

            if ($signatureMode === 'mock' && $statusCode === 402) {
                $steps[] = ['type' => 'facilitator', 'message' => '→ Sent to facilitator for verification', 'ms' => $elapsed + 5];
                $steps[] = ['type' => 'reject', 'message' => '✗ Facilitator rejected mock signature', 'ms' => $elapsed + 80];
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
            $steps[] = ['type' => 'error', 'message' => '✗ ' . $e->getMessage(), 'ms' => $elapsed];

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
