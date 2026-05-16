<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Crypto\HashAlgo;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * Logs x402 payment transactions for revenue analytics.
 */
final class PaymentLogger
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly HashService $hashService,
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
        string $contentType = 'page',
        int $contentUid = 0,
    ): void {
        $connection = $this->connectionPool->getConnectionForTable('tx_x402_payment_log');

        $connection->insert('tx_x402_payment_log', [
            'pid' => $pageUid,
            'tstamp' => time(),
            'crdate' => time(),
            'page_uid' => $pageUid,
            'content_type' => $contentType,
            'content_uid' => $contentUid > 0 ? $contentUid : $pageUid,
            'request_uri' => (string)$request->getUri(),
            'amount' => $amount,
            'currency' => $currency,
            'network' => $network,
            'tx_hash' => $txHash ?? '',
            'payer_address' => $this->extractPayerAddress($request),
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

        try {
            $payload = Json::decodeObject($decoded);
        } catch (\JsonException) {
            return '';
        }

        return ScalarValue::string($payload['from'] ?? ($payload['payer'] ?? null));
    }

    private function hashIpAddress(string $ipAddress): string
    {
        if ($ipAddress === '') {
            return '';
        }

        return $this->hashService->hmac($ipAddress, 'x402-paywall-ip-log', HashAlgo::SHA3_256);
    }
}
