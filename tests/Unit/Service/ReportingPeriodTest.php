<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Service\ReportingPeriod;

final class ReportingPeriodTest extends UnitTestCase
{
    #[Test]
    public function valuesMatchTheMcpToolEnum(): void
    {
        $values = ReportingPeriod::values();

        self::assertSame(['today', '7days', '30days', 'all'], $values);
        self::assertSame(ReportingPeriod::cases(), array_map(ReportingPeriod::from(...), $values));
    }

    #[Test]
    public function sinceIsOrderedAndUnboundedForAll(): void
    {
        $now = time();

        self::assertSame(0, ReportingPeriod::All->since());
        self::assertLessThan(ReportingPeriod::Last7Days->since(), ReportingPeriod::Last30Days->since());
        self::assertLessThan(ReportingPeriod::Today->since(), ReportingPeriod::Last7Days->since());
        self::assertLessThanOrEqual($now, ReportingPeriod::Today->since());
        self::assertGreaterThan($now - 86_400, ReportingPeriod::Today->since());
    }

    #[Test]
    public function everyPeriodHasALabelKey(): void
    {
        foreach (ReportingPeriod::cases() as $period) {
            self::assertStringStartsWith('dashboard.period.', $period->label());
        }
    }
}
