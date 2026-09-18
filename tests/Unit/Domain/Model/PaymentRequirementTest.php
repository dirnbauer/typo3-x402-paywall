<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;

final class PaymentRequirementTest extends UnitTestCase
{
    /**
     * @return iterable<string, array{0: string, 1: int, 2: string}>
     */
    public static function atomicUnitsDataProvider(): iterable
    {
        yield 'one cent usdc' => ['0.01', 6, '10000'];
        yield 'whole token' => ['1', 6, '1000000'];
        yield 'many decimals are truncated' => ['0.1234567', 6, '123456'];
        yield 'comma decimal separator' => ['0,5', 6, '500000'];
        yield 'zero' => ['0', 6, '0'];
        yield 'garbage' => ['free', 6, '0'];
        yield '18 decimals' => ['2.5', 18, '2500000000000000000'];
    }

    #[Test]
    #[DataProvider('atomicUnitsDataProvider')]
    public function toAtomicUnitsConvertsDecimalAmounts(string $amount, int $decimals, string $expected): void
    {
        self::assertSame($expected, PaymentRequirement::toAtomicUnits($amount, $decimals));
    }

    #[Test]
    public function fromAtomicUnitsIsTheInverseConversion(): void
    {
        self::assertSame('0.01', PaymentRequirement::fromAtomicUnits('10000', 6));
        self::assertSame('1', PaymentRequirement::fromAtomicUnits('1000000', 6));
        self::assertSame('0.000001', PaymentRequirement::fromAtomicUnits('1', 6));
        self::assertSame('0', PaymentRequirement::fromAtomicUnits('abc', 6));
    }

    #[Test]
    public function fromConfigProducesX402V2WireFormat(): void
    {
        $config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xReceiver',
            'network' => 'base-sepolia',
            'max_timeout_seconds' => 120,
        ]);

        $requirement = PaymentRequirement::fromConfig($config, '0.05');

        self::assertSame([
            'scheme' => 'exact',
            'network' => 'eip155:84532',
            'amount' => '50000',
            'asset' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
            'payTo' => '0xReceiver',
            'maxTimeoutSeconds' => 120,
            'extra' => ['name' => 'USDC', 'version' => '2'],
        ], $requirement->toArray());
    }

    #[Test]
    public function matchesComparesTheProtocolRelevantFieldsCaseInsensitively(): void
    {
        $config = PaywallConfiguration::fromArray(['enabled' => true, 'wallet_address' => '0xAbCd', 'network' => 'base-sepolia']);
        $requirement = PaymentRequirement::fromConfig($config, '0.01');

        $accepted = $requirement->toArray();
        $accepted['payTo'] = strtolower($accepted['payTo']);
        $accepted['asset'] = strtoupper($accepted['asset']);
        self::assertTrue($requirement->matches($accepted));

        $accepted['amount'] = '9999';
        self::assertFalse($requirement->matches($accepted));
    }

    #[Test]
    public function fromArrayIgnoresUnknownFieldsAndDefaultsTheTimeout(): void
    {
        $requirement = PaymentRequirement::fromArray([
            'scheme' => 'exact',
            'network' => 'eip155:84532',
            'amount' => '10000',
            'asset' => '0xToken',
            'payTo' => '0xReceiver',
            'outputSchema' => ['type' => 'object'],
            'extra' => ['name' => 'USDC'],
        ]);

        self::assertSame(300, $requirement->maxTimeoutSeconds);
        self::assertSame(['name' => 'USDC'], $requirement->extra);
        self::assertArrayNotHasKey('outputSchema', $requirement->toArray());
    }
}
