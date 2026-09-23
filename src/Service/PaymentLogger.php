<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Service;

use Doctrine\DBAL\ParameterType;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Crypto\HashAlgo;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use Webconsulting\X402Paywall\Domain\Model\SettlementResponse;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * Writes and reads the payment log (tx_x402_payment_log) used by the dashboard and the MCP tools.
 */
final readonly class PaymentLogger
{
    public const string TABLE = 'tx_x402_payment_log';

    public const string STATUS_SETTLED = 'settled';
    /** Broadcast but unconfirmed, or no answer from the facilitator: check the transaction on chain. */
    public const string STATUS_PENDING = 'pending';
    public const string STATUS_FAILED = 'failed';

    public function __construct(
        private ConnectionPool $connectionPool,
        private HashService $hashService,
    ) {}

    /**
     * Records a settlement attempt (settled, pending or failed) for the paid resource.
     *
     * @param string $contentType Record type behind the page ("page", "news", ...), see ContentTypeResolver
     * @param int $contentUid Record UID; 0 falls back to the page UID
     * @param string $amount Human-readable price ("0.05")
     */
    public function logPayment(
        ServerRequestInterface $request,
        int $pageUid,
        string $contentType,
        int $contentUid,
        string $amount,
        string $currency,
        SettlementResponse $settlement,
    ): void {
        $now = time();
        $this->connectionPool->getConnectionForTable(self::TABLE)->insert(self::TABLE, [
            'pid' => $pageUid,
            'tstamp' => $now,
            'crdate' => $now,
            'page_uid' => $pageUid,
            'content_type' => $contentType,
            'content_uid' => $contentUid > 0 ? $contentUid : $pageUid,
            'request_uri' => (string)$request->getUri(),
            'amount' => $amount,
            'currency' => $currency,
            'network' => $settlement->network,
            'tx_hash' => $settlement->transaction,
            'payer_address' => $settlement->payer,
            'facilitator_response' => $settlement->raw !== [] ? Json::encode($settlement->raw) : '',
            'status' => self::status($settlement),
            'user_agent' => substr($request->getHeaderLine('User-Agent'), 0, 500),
            'ip_hash' => $this->hashIpAddress(ScalarValue::string($request->getServerParams()['REMOTE_ADDR'] ?? null)),
        ]);
    }

    public static function status(SettlementResponse $settlement): string
    {
        return match (true) {
            $settlement->success => self::STATUS_SETTLED,
            $settlement->isPending() => self::STATUS_PENDING,
            default => self::STATUS_FAILED,
        };
    }

    /**
     * Settled revenue per currency (a site may change its asset, so amounts are never summed across currencies).
     *
     * @return list<array{currency: string, transactions: int, revenue: string}> Highest revenue first
     */
    public function getRevenueByCurrency(int $since = 0): array
    {
        $rows = $this->settledSince($since)
            ->select('currency')
            ->addSelectLiteral('COUNT(*) AS transactions')
            ->addSelectLiteral('COALESCE(SUM(CAST(amount AS DECIMAL(20,6))), 0) AS revenue')
            ->groupBy('currency')
            ->orderBy('revenue', 'DESC')
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(static fn(array $row): array => [
            'currency' => ScalarValue::string($row['currency'] ?? null),
            'transactions' => ScalarValue::int($row['transactions'] ?? null),
            'revenue' => self::formatAmount(ScalarValue::float($row['revenue'] ?? null)),
        ], $rows);
    }

    /**
     * Number of logged settlement attempts per status.
     *
     * @return array{settled: int, pending: int, failed: int}
     */
    public function countByStatus(int $since = 0): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->select('status')
            ->addSelectLiteral('COUNT(*) AS attempts')
            ->from(self::TABLE)
            ->groupBy('status');
        if ($since > 0) {
            $queryBuilder->where($queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($since, ParameterType::INTEGER)));
        }

        $counts = [self::STATUS_SETTLED => 0, self::STATUS_PENDING => 0, self::STATUS_FAILED => 0];
        foreach ($queryBuilder->executeQuery()->fetchAllAssociative() as $row) {
            $status = ScalarValue::string($row['status'] ?? null);
            if (array_key_exists($status, $counts)) {
                $counts[$status] = ScalarValue::int($row['attempts'] ?? null);
            }
        }

        return $counts;
    }

    /**
     * @return array{total_transactions: int, total_revenue: float, period_start: string}
     */
    public function getStats(int $since = 0): array
    {
        $queryBuilder = $this->settledSince($since);
        $row = $queryBuilder
            ->addSelectLiteral('COUNT(*) AS cnt')
            ->addSelectLiteral('COALESCE(SUM(CAST(amount AS DECIMAL(20,6))), 0) AS revenue')
            ->executeQuery()
            ->fetchAssociative();
        $row = is_array($row) ? $row : [];

        return [
            'total_transactions' => ScalarValue::int($row['cnt'] ?? null),
            'total_revenue' => round(ScalarValue::float($row['revenue'] ?? null), 6),
            'period_start' => $since > 0 ? date('Y-m-d', $since) : 'all time',
        ];
    }

    /**
     * @return list<array<string, mixed>> Rows with page_uid, transactions, revenue; highest revenue first
     */
    public function getTopPages(int $limit = 10, int $since = 0): array
    {
        return $this->settledSince($since)
            ->select('page_uid')
            ->addSelectLiteral('COUNT(*) AS transactions')
            ->addSelectLiteral('SUM(CAST(amount AS DECIMAL(20,6))) AS revenue')
            ->groupBy('page_uid')
            ->orderBy('revenue', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    /**
     * @return list<array<string, mixed>> Newest first
     */
    public function getRecentTransactions(int $limit = 20): array
    {
        return $this->connectionPool->getQueryBuilderForTable(self::TABLE)
            ->select('uid', 'crdate', 'page_uid', 'content_type', 'content_uid', 'amount', 'currency', 'network', 'tx_hash', 'payer_address', 'status', 'request_uri')
            ->from(self::TABLE)
            ->orderBy('crdate', 'DESC')
            ->addOrderBy('uid', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function settledSince(int $since): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter(self::STATUS_SETTLED)));
        if ($since > 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($since, ParameterType::INTEGER)));
        }

        return $queryBuilder;
    }

    /**
     * "12.5" for 12.500000: at most six decimals (USDC precision), no trailing zeros.
     */
    private static function formatAmount(float $amount): string
    {
        $formatted = rtrim(rtrim(number_format($amount, 6, '.', ''), '0'), '.');

        return $formatted === '' || $formatted === '-0' ? '0' : $formatted;
    }

    private function hashIpAddress(string $ipAddress): string
    {
        return $ipAddress === '' ? '' : $this->hashService->hmac($ipAddress, 'x402-paywall-ip-log', HashAlgo::SHA3_256);
    }
}
