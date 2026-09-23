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
use Webconsulting\X402Paywall\Service\ReportingPeriod;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * MCP tool "x402_stats": revenue statistics from the payment log.
 */
final class X402StatsTool extends AbstractMcpTool
{
    public const string NAME = 'x402_stats';

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
            . 'transaction count and the top five pages for a period. Valid periods: ' . implode(', ', ReportingPeriod::values()) . '.';
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
                    'enum' => ReportingPeriod::values(),
                    'description' => 'Time period for the statistics',
                    'default' => ReportingPeriod::Last30Days->value,
                ],
            ],
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function doExecute(array $args): string
    {
        $period = ReportingPeriod::tryFrom(ScalarValue::string($args['period'] ?? null)) ?? ReportingPeriod::Last30Days;
        $stats = $this->paymentLogger->getStats($period->since());

        return Json::encode([
            'period' => $period->value,
            'total_revenue' => $stats['total_revenue'],
            'total_transactions' => $stats['total_transactions'],
            'top_pages' => array_map(static fn(array $page): array => [
                'page_uid' => ScalarValue::int($page['page_uid'] ?? null),
                'transactions' => ScalarValue::int($page['transactions'] ?? null),
                'revenue' => round(ScalarValue::float($page['revenue'] ?? null), 6),
            ], $this->paymentLogger->getTopPages(5, $period->since())),
        ], JSON_PRETTY_PRINT);
    }
}
