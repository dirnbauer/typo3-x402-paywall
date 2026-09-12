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
 * MCP tool "x402_stats": revenue statistics from the payment log.
 */
final class X402StatsTool extends AbstractMcpTool
{
    public const NAME = 'x402_stats';

    public function __construct(
        private readonly PaymentLogger $paymentLogger,
    ) {}

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDescription(): string
    {
        return 'Get x402 payment revenue statistics: settled revenue (in the configured currency, USDC by default), '
            . 'transaction count and the top five pages for a period. Valid periods: today, 7days, 30days, all.';
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
                    'description' => 'Time period for the statistics',
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
        $period = ScalarValue::string($args['period'] ?? null, '30days');

        $since = match ($period) {
            'today' => $this->timestamp('today'),
            '7days' => $this->timestamp('-7 days'),
            '30days' => $this->timestamp('-30 days'),
            default => 0,
        };

        $stats = $this->paymentLogger->getStats($since);
        $topPages = $this->paymentLogger->getTopPages(5, $since);

        return Json::encode([
            'period' => $period,
            'total_revenue' => $stats['total_revenue'],
            'total_transactions' => $stats['total_transactions'],
            'top_pages' => array_map(static fn(array $page): array => [
                'page_uid' => ScalarValue::int($page['page_uid'] ?? null),
                'transactions' => ScalarValue::int($page['transactions'] ?? null),
                'revenue' => round(ScalarValue::float($page['revenue'] ?? null), 6),
            ], $topPages),
        ], JSON_PRETTY_PRINT);
    }

    private function timestamp(string $modifier): int
    {
        $timestamp = strtotime($modifier);

        return $timestamp === false ? 0 : $timestamp;
    }
}
