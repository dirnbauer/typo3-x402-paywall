<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Mcp\Tool;

use Webconsulting\X402Paywall\Service\PaymentLogger;

/**
 * MCP Tool: get x402 payment revenue statistics.
 *
 * Example agent interaction:
 *   Agent: "How much revenue did we earn this week?"
 *   Tool:  { total_revenue: 12.45, total_transactions: 87, period: "last 7 days" }
 */
final class X402StatsTool extends AbstractMcpTool
{
    public function __construct(
        private readonly PaymentLogger $paymentLogger,
    ) {}

    public function getName(): string
    {
        return 'x402_stats';
    }

    public function getDescription(): string
    {
        return 'Get x402 payment revenue statistics. '
             . 'Returns total USDC earned and transaction count for a given period. '
             . 'Valid periods: today, 7days, 30days, all.';
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
                'period' => [
                    'type' => 'string',
                    'enum' => ['today', '7days', '30days', 'all'],
                    'description' => 'Time period for the stats',
                    'default' => '30days',
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function doExecute(array $args): string
    {
        $period = (string)($args['period'] ?? '30days');

        $since = match ($period) {
            'today' => strtotime('today') ?: 0,
            '7days' => strtotime('-7 days') ?: 0,
            '30days' => strtotime('-30 days') ?: 0,
            default => 0,
        };

        $stats = $this->paymentLogger->getStats($since);
        $topPages = $this->paymentLogger->getTopPages(5, $since);

        return json_encode([
            'period' => $period,
            'total_revenue_usdc' => $stats['total_revenue'],
            'total_transactions' => $stats['total_transactions'],
            'top_pages' => array_map(static fn(array $p) => [
                'page_uid' => (int)$p['page_uid'],
                'transactions' => (int)$p['transactions'],
                'revenue_usdc' => round((float)$p['revenue'], 4),
            ], $topPages),
        ], JSON_PRETTY_PRINT);
    }
}
