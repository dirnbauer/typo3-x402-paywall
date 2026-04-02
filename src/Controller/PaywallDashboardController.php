<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Page\PageRenderer;
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
