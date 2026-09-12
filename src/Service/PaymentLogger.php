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
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * Writes and reads the payment log (tx_x402_payment_log) used by the dashboard and the MCP tools.
 */
final class PaymentLogger
{
    public const TABLE = 'tx_x402_payment_log';

    public const STATUS_SETTLED = 'settled';
    public const STATUS_FAILED = 'failed';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly HashService $hashService,
    ) {}

    /**
     * @param array<string, mixed> $settlementDetails Raw facilitator response, stored for auditing
     */
    public function logPayment(
        ServerRequestInterface $request,
        int $pageUid,
        string $amount,
        string $currency,
        string $network,
        ?string $txHash,
        string $status = self::STATUS_SETTLED,
        array $settlementDetails = [],
        string $contentType = 'page',
        int $contentUid = 0,
        string $payerAddress = '',
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
            'network' => $network,
            'tx_hash' => $txHash ?? '',
            'payer_address' => $payerAddress,
            'facilitator_response' => $settlementDetails !== [] ? Json::encode($settlementDetails) : '',
            'status' => $status,
            'user_agent' => substr($request->getHeaderLine('User-Agent'), 0, 500),
            'ip_hash' => $this->hashIpAddress(ScalarValue::string($request->getServerParams()['REMOTE_ADDR'] ?? null)),
        ]);
    }

    /**
     * @return array{total_transactions: int, total_revenue: float, period_start: string}
     */
    public function getStats(int $since = 0): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->addSelectLiteral('COUNT(*) AS cnt')
            ->addSelectLiteral('COALESCE(SUM(CAST(amount AS DECIMAL(20,6))), 0) AS revenue')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter(self::STATUS_SETTLED)));

        if ($since > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($since, ParameterType::INTEGER)),
            );
        }

        $row = $queryBuilder->executeQuery()->fetchAssociative();
        if (!is_array($row)) {
            $row = [];
        }

        return [
            'total_transactions' => ScalarValue::int($row['cnt'] ?? null),
            'total_revenue' => round(ScalarValue::float($row['revenue'] ?? null), 6),
            'period_start' => $since > 0 ? date('Y-m-d', $since) : 'all time',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTopPages(int $limit = 10, int $since = 0): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder
            ->select('page_uid')
            ->addSelectLiteral('COUNT(*) AS transactions')
            ->addSelectLiteral('SUM(CAST(amount AS DECIMAL(20,6))) AS revenue')
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter(self::STATUS_SETTLED)))
            ->groupBy('page_uid')
            ->orderBy('revenue', 'DESC')
            ->setMaxResults($limit);

        if ($since > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($since, ParameterType::INTEGER)),
            );
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentTransactions(int $limit = 20): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);

        return $queryBuilder
            ->select('uid', 'crdate', 'page_uid', 'content_type', 'content_uid', 'amount', 'currency', 'network', 'tx_hash', 'payer_address', 'status', 'request_uri')
            ->from(self::TABLE)
            ->orderBy('crdate', 'DESC')
            ->addOrderBy('uid', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function hashIpAddress(string $ipAddress): string
    {
        if ($ipAddress === '') {
            return '';
        }

        return $this->hashService->hmac($ipAddress, 'x402-paywall-ip-log', HashAlgo::SHA3_256);
    }
}
