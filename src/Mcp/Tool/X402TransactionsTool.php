<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Mcp\Tool;

use Webconsulting\X402Paywall\Service\PaymentLogger;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

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
        $limit = min(50, max(1, ScalarValue::int($args['limit'] ?? null, 10)));
        $rows = $this->paymentLogger->getRecentTransactions($limit);

        $transactions = array_map(static fn(array $row) => [
            'uid' => ScalarValue::int($row['uid'] ?? null),
            'date' => date('Y-m-d H:i:s', ScalarValue::int($row['crdate'] ?? null)),
            'page_uid' => ScalarValue::int($row['page_uid'] ?? null),
            'content_type' => ScalarValue::string($row['content_type'] ?? null, 'page'),
            'content_uid' => ScalarValue::int($row['content_uid'] ?? ($row['page_uid'] ?? null)),
            'amount_usdc' => ScalarValue::string($row['amount'] ?? null),
            'currency' => ScalarValue::string($row['currency'] ?? null),
            'network' => ScalarValue::string($row['network'] ?? null),
            'status' => ScalarValue::string($row['status'] ?? null),
            'tx_hash' => self::shortHash($row['tx_hash'] ?? null),
        ], $rows);

        return Json::encode([
            'count' => count($transactions),
            'transactions' => $transactions,
        ], JSON_PRETTY_PRINT);
    }

    private static function shortHash(mixed $value): ?string
    {
        $hash = ScalarValue::string($value);

        return $hash === '' ? null : substr($hash, 0, 18) . '...';
    }
}
