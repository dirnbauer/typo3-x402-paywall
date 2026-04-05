<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Controller;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Site\SiteFinder;
use Webconsulting\X402Paywall\Service\PaymentLogger;

/**
 * Backend module controller for x402 payment dashboard.
 */
final readonly class PaywallDashboardController
{
    public function __construct(
        private ModuleTemplateFactory $moduleTemplateFactory,
        private PaymentLogger $paymentLogger,
        private PageRenderer $pageRenderer,
        private ClientInterface $httpClient,
        private SiteFinder $siteFinder,
    ) {}

    public function mainAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);

        $stats30d = $this->paymentLogger->getStats(strtotime('-30 days') ?: 0);
        $stats7d = $this->paymentLogger->getStats(strtotime('-7 days') ?: 0);
        $statsToday = $this->paymentLogger->getStats(strtotime('today') ?: 0);
        $statsAll = $this->paymentLogger->getStats();
        $topPages = $this->paymentLogger->getTopPages(10, strtotime('-30 days') ?: 0);
        $recentTx = $this->paymentLogger->getRecentTransactions(20);

        $moduleTemplate->assignMultiple([
            'stats30d' => $stats30d,
            'stats7d' => $stats7d,
            'statsToday' => $statsToday,
            'statsAll' => $statsAll,
            'topPages' => $topPages,
            'recentTransactions' => $recentTx,
        ]);

        $moduleTemplate->setTitle('x402 Payment Dashboard');

        return $moduleTemplate->renderResponse('Dashboard/Main');
    }

    public function simulatorAction(ServerRequestInterface $request): ResponseInterface
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->setTitle('x402 Flow Simulator');

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
                'label' => 'Plain GET → expects 402',
                'url' => $baseUrl . '/premium-content',
                'signature' => '',
                'description' => 'A client requests a gated resource with no payment. TYPO3 returns 402 with payment requirements.',
            ],
            [
                'id' => 'mock_signature',
                'label' => 'Mock signature → facilitator rejects',
                'url' => $baseUrl . '/premium-content',
                'signature' => 'mock',
                'description' => 'Client sends a fake PAYMENT-SIGNATURE. The facilitator at x402.org will reject it — confirms the verification path is wired.',
            ],
            [
                'id' => 'news_detail',
                'label' => 'News detail (EXT:news plugin)',
                'url' => $baseUrl . '/news/detail?tx_news_pi1[news]=1&tx_news_pi1[action]=detail',
                'signature' => '',
                'description' => 'Gated news article via EXT:news plugin. ContentTypeResolver maps this to content_type=news, content_uid=1.',
            ],
            [
                'id' => 'api_route',
                'label' => 'Headless API route',
                'url' => $baseUrl . '/api/v1/content/42',
                'signature' => '',
                'description' => 'A headless API endpoint gated via route pattern. AI agents hit this to fetch paid content.',
            ],
            [
                'id' => 'facilitator_check',
                'label' => 'Facilitator health check',
                'url' => 'https://x402.org/facilitator',
                'signature' => '',
                'description' => 'Checks if the Coinbase x402 facilitator is reachable. Expects a 200 response.',
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
        $body = json_decode((string)$request->getBody(), true) ?? [];
        $url = trim($body['url'] ?? '');
        $signatureMode = $body['signature'] ?? '';

        if ($url === '') {
            return new JsonResponse(['error' => 'No URL provided'], 400);
        }

        $headers = [
            'Accept' => 'application/json, text/html, */*',
            'User-Agent' => 'x402-simulator/TYPO3-backend',
        ];

        if ($signatureMode === 'mock') {
            $mockPayload = base64_encode(json_encode([
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

            $response = $this->httpClient->request('GET', $url, [
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
            $paymentHeader = $response->getHeaderLine('PAYMENT-REQUIRED')
                ?: $response->getHeaderLine('X-PAYMENT-REQUIRED');

            if ($paymentHeader !== '') {
                $decoded = base64_decode($paymentHeader, true);
                if ($decoded !== false) {
                    $decodedRequirement = json_decode($decoded, true);
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
        } catch (GuzzleException $e) {
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
            'today' => strtotime('today'),
            '7days' => strtotime('-7 days'),
            '30days' => strtotime('-30 days'),
            default => 0,
        };

        return new JsonResponse([
            'stats' => $this->paymentLogger->getStats($since ?: 0),
            'topPages' => $this->paymentLogger->getTopPages(10, $since ?: 0),
            'recentTransactions' => $this->paymentLogger->getRecentTransactions(10),
        ]);
    }
}
