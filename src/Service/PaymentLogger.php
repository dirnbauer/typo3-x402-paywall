<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Logs x402 payment transactions for revenue analytics.
 */
final class PaymentLogger
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @param array<string, mixed> $settlementDetails
     */
    public function logPayment(
        ServerRequestInterface $request,
        int $pageUid,
        string $amount,
        string $currency,
        string $network,
        ?string $txHash,
        string $status = 'settled',
        array $settlementDetails = [],
    ): void {
        $connection = $this->connectionPool->getConnectionForTable('tx_x402_payment_log');

        $connection->insert('tx_x402_payment_log', [
            'pid' => 0,
            'tstamp' => time(),
            'crdate' => time(),
            'page_uid' => $pageUid,
            'request_uri' => (string)$request->getUri(),
            'amount' => $amount,
            'currency' => $currency,
            'network' => $network,
            'tx_hash' => $txHash ?? '',
            'payer_address' => $this->extractPayerAddress($request),
            'facilitator_response' => !empty($settlementDetails) ? json_encode($settlementDetails) : '',
            'status' => $status,
            'user_agent' => substr($request->getHeaderLine('User-Agent'), 0, 500),
            'ip_hash' => hash('sha256', $request->getServerParams()['REMOTE_ADDR'] ?? ''),
        ]);
    }

    /**
     * @return array{total_transactions: int, total_revenue: float, period_start: string}
     */
    public function getStats(int $since = 0): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_x402_payment_log');
        $queryBuilder
            ->addSelectLiteral('COUNT(*) as cnt')
            ->addSelectLiteral('COALESCE(SUM(CAST(amount as DECIMAL(20,6))), 0) as revenue')
            ->from('tx_x402_payment_log')
            ->where($queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter('settled')));

        if ($since > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($since, \Doctrine\DBAL\ParameterType::INTEGER))
            );
        }

        $row = $queryBuilder->executeQuery()->fetchAssociative();

        return [
            'total_transactions' => (int)($row['cnt'] ?? 0),
            'total_revenue' => round((float)($row['revenue'] ?? 0), 6),
            'period_start' => $since > 0 ? date('Y-m-d', $since) : 'all time',
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getTopPages(int $limit = 10, int $since = 0): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_x402_payment_log');
        $queryBuilder
            ->select('page_uid')
            ->addSelectLiteral('COUNT(*) as transactions')
            ->addSelectLiteral('SUM(CAST(amount as DECIMAL(20,6))) as revenue')
            ->from('tx_x402_payment_log')
            ->where($queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter('settled')))
            ->groupBy('page_uid')
            ->orderBy('revenue', 'DESC')
            ->setMaxResults($limit);

        if ($since > 0) {
            $queryBuilder->andWhere(
                $queryBuilder->expr()->gte('crdate', $queryBuilder->createNamedParameter($since, \Doctrine\DBAL\ParameterType::INTEGER))
            );
        }

        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getRecentTransactions(int $limit = 20): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_x402_payment_log');

        return $queryBuilder
            ->select('uid', 'crdate', 'page_uid', 'amount', 'currency', 'network', 'tx_hash', 'status', 'request_uri')
            ->from('tx_x402_payment_log')
            ->orderBy('crdate', 'DESC')
            ->setMaxResults($limit)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function extractPayerAddress(ServerRequestInterface $request): string
    {
        $paymentHeader = $request->getHeaderLine('PAYMENT-SIGNATURE');
        if ($paymentHeader === '') {
            return '';
        }

        $decoded = base64_decode($paymentHeader, true);
        if ($decoded === false) {
            return '';
        }

        $payload = json_decode($decoded, true);
        return (string)($payload['from'] ?? $payload['payer'] ?? '');
    }
}
