<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Mcp\Tool;

use Webconsulting\X402Paywall\Service\PaymentLogger;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * MCP tool "x402_transactions": lists recent entries of the payment log.
 */
final class X402TransactionsTool extends AbstractMcpTool
{
    public const string NAME = 'x402_transactions';

    public function __construct(
        private readonly PaymentLogger $paymentLogger,
    ) {}

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDescription(): string
    {
        return 'List recent x402 payment transactions from the payment log: amount, currency, content type '
            . '(page/news/event/blog_post), content UID, CAIP-2 network, status (settled/failed), payer wallet '
            . 'and transaction hash. Use this to audit payments or debug the payment flow.';
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

        $transactions = array_map(static fn(array $row): array => [
            'uid' => ScalarValue::int($row['uid'] ?? null),
            'date' => date('Y-m-d H:i:s', ScalarValue::int($row['crdate'] ?? null)),
            'page_uid' => ScalarValue::int($row['page_uid'] ?? null),
            'content_type' => ScalarValue::string($row['content_type'] ?? null, 'page'),
            'content_uid' => ScalarValue::int($row['content_uid'] ?? ($row['page_uid'] ?? null)),
            'amount' => ScalarValue::string($row['amount'] ?? null),
            'currency' => ScalarValue::string($row['currency'] ?? null),
            'network' => ScalarValue::string($row['network'] ?? null),
            'status' => ScalarValue::string($row['status'] ?? null),
            'payer' => ScalarValue::string($row['payer_address'] ?? null),
            'tx_hash' => ScalarValue::string($row['tx_hash'] ?? null),
            'request_uri' => ScalarValue::string($row['request_uri'] ?? null),
        ], $rows);

        return Json::encode([
            'count' => count($transactions),
            'transactions' => $transactions,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
