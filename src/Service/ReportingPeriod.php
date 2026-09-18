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

/**
 * Reporting periods of the dashboard and the x402_stats MCP tool.
 */
enum ReportingPeriod: string
{
    case Today = 'today';
    case Last7Days = '7days';
    case Last30Days = '30days';
    case All = 'all';

    /**
     * Unix timestamp the period starts at; 0 means no lower bound.
     */
    public function since(): int
    {
        return match ($this) {
            self::Today => strtotime('today'),
            self::Last7Days => strtotime('-7 days'),
            self::Last30Days => strtotime('-30 days'),
            self::All => 0,
        };
    }

    /**
     * Key in locallang_mod.xlf.
     */
    public function label(): string
    {
        return match ($this) {
            self::Today => 'dashboard.period.today',
            self::Last7Days => 'dashboard.period.last_7_days',
            self::Last30Days => 'dashboard.period.last_30_days',
            self::All => 'dashboard.period.all_time',
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn(self $period): string => $period->value, self::cases());
    }
}
