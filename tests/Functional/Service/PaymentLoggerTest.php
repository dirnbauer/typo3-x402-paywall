<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Functional\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Webconsulting\X402Paywall\Mcp\Tool\X402GatedPagesTool;
use Webconsulting\X402Paywall\Mcp\Tool\X402StatsTool;
use Webconsulting\X402Paywall\Mcp\Tool\X402TransactionsTool;
use Webconsulting\X402Paywall\Service\PaymentLogger;
use Webconsulting\X402Paywall\Utility\Json;

/**
 * Runs the payment log queries (DECIMAL casts, grouping, ordering) and the log-backed MCP tools against a real database.
 */
final class PaymentLoggerTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['webconsulting/typo3-x402-paywall'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_x402_payment_log.csv');
    }

    #[Test]
    public function statsCountOnlySettledPaymentsWithinThePeriod(): void
    {
        $logger = $this->get(PaymentLogger::class);

        $all = $logger->getStats();
        self::assertSame(3, $all['total_transactions']);
        self::assertSame(1.6, $all['total_revenue']);
        self::assertSame('all time', $all['period_start']);

        $recent = $logger->getStats(1700086400);
        self::assertSame(2, $recent['total_transactions']);
        self::assertSame(1.55, $recent['total_revenue']);
        self::assertSame(date('Y-m-d', 1700086400), $recent['period_start']);
    }

    #[Test]
    public function topPagesAreOrderedByRevenue(): void
    {
        $topPages = $this->get(PaymentLogger::class)->getTopPages(10);

        self::assertCount(2, $topPages);
        self::assertSame(3, (int)$topPages[0]['page_uid']);
        self::assertSame(1, (int)$topPages[0]['transactions']);
        self::assertSame(1.5, (float)$topPages[0]['revenue']);
        self::assertSame(2, (int)$topPages[1]['page_uid']);
        self::assertSame(2, (int)$topPages[1]['transactions']);
        self::assertSame(0.1, (float)$topPages[1]['revenue']);
    }

    #[Test]
    public function recentTransactionsAreNewestFirst(): void
    {
        $rows = $this->get(PaymentLogger::class)->getRecentTransactions(3);

        self::assertSame([4, 3, 2], array_map(static fn(array $row): int => (int)$row['uid'], $rows));
        self::assertSame('failed', $rows[0]['status']);
    }

    #[Test]
    public function revenueIsReportedPerCurrencyAndAttemptsPerStatus(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_x402_payment_log_more.csv');
        $logger = $this->get(PaymentLogger::class);

        self::assertSame([
            ['currency' => 'EURC', 'transactions' => 1, 'revenue' => '2'],
            ['currency' => 'USDC', 'transactions' => 3, 'revenue' => '1.6'],
        ], $logger->getRevenueByCurrency());
        self::assertSame([['currency' => 'EURC', 'transactions' => 1, 'revenue' => '2']], $logger->getRevenueByCurrency(1700400000));
        self::assertSame(['settled' => 4, 'pending' => 1, 'failed' => 1], $logger->countByStatus());
        self::assertSame(['settled' => 1, 'pending' => 1, 'failed' => 0], $logger->countByStatus(1700300000));
    }

    #[Test]
    public function mcpToolsReportTheLog(): void
    {
        $stats = Json::decodeObject($this->get(X402StatsTool::class)->execute(['period' => 'all']));
        self::assertSame('all', $stats['period']);
        self::assertSame(3, $stats['total_transactions']);
        self::assertSame(1.6, $stats['total_revenue']);
        self::assertSame([['page_uid' => 3, 'transactions' => 1, 'revenue' => 1.5], ['page_uid' => 2, 'transactions' => 2, 'revenue' => 0.1]], $stats['top_pages']);

        $transactions = Json::decodeObject($this->get(X402TransactionsTool::class)->execute(['limit' => 2]));
        self::assertSame(2, $transactions['count']);
        self::assertIsArray($transactions['transactions']);
        self::assertSame(['uid' => 4, 'status' => 'failed', 'payer' => '0xPayer3'], array_intersect_key($transactions['transactions'][0], ['uid' => 1, 'status' => 1, 'payer' => 1]));
        self::assertSame(['uid' => 3, 'content_type' => 'news', 'content_uid' => 42, 'tx_hash' => '0xccc'], array_intersect_key($transactions['transactions'][1], ['uid' => 1, 'content_type' => 1, 'content_uid' => 1, 'tx_hash' => 1]));

        $gated = Json::decodeObject($this->get(X402GatedPagesTool::class)->execute([]));
        self::assertSame(1, $gated['count']);
        self::assertSame([['uid' => 2, 'title' => 'Premium', 'slug' => '/premium', 'price' => '0.05', 'description' => 'Premium article']], $gated['pages']);
    }
}
