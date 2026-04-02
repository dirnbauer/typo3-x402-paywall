<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;

final class PaywallConfigurationTest extends TestCase
{
    public function testDefaultConfigIsDisabled(): void
    {
        $config = new PaywallConfiguration();
        self::assertFalse($config->enabled);
        self::assertFalse($config->isValid());
    }

    public function testFromArrayCreatesValidConfig(): void
    {
        $config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0x1234567890abcdef',
            'network' => 'base',
            'default_price' => '0.05',
            'currency' => 'USDC',
        ]);

        self::assertTrue($config->enabled);
        self::assertTrue($config->isValid());
        self::assertSame('0x1234567890abcdef', $config->walletAddress);
        self::assertSame('0.05', $config->defaultPrice);
    }

    public function testIsValidRequiresWalletAddress(): void
    {
        $config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '',
        ]);

        self::assertFalse($config->isValid());
    }

    public function testCaip2NetworkIds(): void
    {
        $base = PaywallConfiguration::fromArray(['network' => 'base']);
        self::assertSame('eip155:8453', $base->getCaip2NetworkId());

        $sepolia = PaywallConfiguration::fromArray(['network' => 'base-sepolia']);
        self::assertSame('eip155:84532', $sepolia->getCaip2NetworkId());

        $polygon = PaywallConfiguration::fromArray(['network' => 'polygon']);
        self::assertSame('eip155:137', $polygon->getCaip2NetworkId());
    }

    public function testFreeRoutesAndGatedPatterns(): void
    {
        $config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xabc',
            'free_routes' => ['/api/health', '/'],
            'gated_route_patterns' => ['/api/v1/content/*'],
        ]);

        self::assertCount(2, $config->freeRoutes);
        self::assertCount(1, $config->gatedRoutePatterns);
        self::assertContains('/api/health', $config->freeRoutes);
    }

    public function testGatedPageUidsCastToInt(): void
    {
        $config = PaywallConfiguration::fromArray([
            'gated_page_uids' => ['42', '100', '7'],
        ]);

        self::assertSame([42, 100, 7], $config->gatedPageUids);
    }
}
