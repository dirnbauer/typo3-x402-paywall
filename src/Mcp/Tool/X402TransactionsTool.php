<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Mcp\Tool;

use Webconsulting\X402Paywall\Service\PaymentLogger;

/**
 * MCP Tool: list recent x402 payment transactions.
 *
 * Example agent interaction:
 *   Agent: "Show me the last 5 payments and what content they were for."
 *   Tool:  [{ amount: "0.01 USDC", content_type: "news", content_uid: 42, tx_hash: "0x..." }]
 */
final class X402TransactionsTool extends AbstractMcpTool
{
    public function __construct(
        private readonly PaymentLogger $paymentLogger,
    ) {}

    public function getName(): string
    {
        return 'x402_transactions';
    }

    public function getDescription(): string
    {
        return 'List recent x402 payment transactions from the log. '
             . 'Shows amount, content type (page/news/event/blog_post), '
             . 'content UID, network, tx_hash, and payer wallet. '
             . 'Use this to audit payments or debug the payment flow.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return $this->getInputSchema();
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'limit' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'maximum' => 50,
                    'default' => 10,
                    'description' => 'Number of transactions to return',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function doExecute(array $args): string
    {
        $limit = min(50, max(1, (int)($args['limit'] ?? 10)));
        $rows = $this->paymentLogger->getRecentTransactions($limit);

        $transactions = array_map(static fn(array $row) => [
            'uid' => (int)$row['uid'],
            'date' => date('Y-m-d H:i:s', (int)$row['crdate']),
            'page_uid' => (int)$row['page_uid'],
            'content_type' => $row['content_type'] ?? 'page',
            'content_uid' => (int)($row['content_uid'] ?? $row['page_uid']),
            'amount_usdc' => $row['amount'],
            'currency' => $row['currency'],
            'network' => $row['network'],
            'status' => $row['status'],
            'tx_hash' => $row['tx_hash'] ? substr($row['tx_hash'], 0, 18) . '...' : null,
        ], $rows);

        return json_encode([
            'count' => count($transactions),
            'transactions' => $transactions,
        ], JSON_PRETTY_PRINT);
    }
}
