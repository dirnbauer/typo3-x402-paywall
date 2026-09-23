<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;

final class PaywallConfigurationTest extends UnitTestCase
{
    #[Test]
    public function defaultConfigurationIsDisabledAndInvalid(): void
    {
        $config = new PaywallConfiguration();

        self::assertFalse($config->enabled);
        self::assertFalse($config->isValid());
        self::assertSame(PaywallConfiguration::DEFAULT_FACILITATOR_URL, $config->facilitatorUrl);
    }

    #[Test]
    public function fromArrayMapsSiteConfigurationKeys(): void
    {
        $config = PaywallConfiguration::fromArray([
            'enabled' => '1',
            'wallet_address' => '0x1234567890abcdef',
            'network' => 'base',
            'default_price' => '0.05',
            'currency' => 'USDC',
            'max_timeout_seconds' => '120',
            'legacy_v1' => 'true',
            'free_routes' => ['/api/health', '/'],
            'gated_route_patterns' => ['/api/v1/content/*'],
            'gated_page_uids' => ['42', '100', '7', 'x'],
        ]);

        self::assertTrue($config->enabled);
        self::assertTrue($config->isValid());
        self::assertSame('0x1234567890abcdef', $config->walletAddress);
        self::assertSame('0.05', $config->defaultPrice);
        self::assertSame(120, $config->maxTimeoutSeconds);
        self::assertTrue($config->legacyV1);
        self::assertSame(['/api/health', '/'], $config->freeRoutes);
        self::assertSame(['/api/v1/content/*'], $config->gatedRoutePatterns);
        self::assertSame([42, 100, 7], $config->gatedPageUids);
    }

    #[Test]
    public function isValidRequiresWalletAddress(): void
    {
        $config = PaywallConfiguration::fromArray(['enabled' => true, 'wallet_address' => '']);

        self::assertFalse($config->isValid());
    }

    #[Test]
    public function isValidRequiresKnownAssetForNonUsdcCurrency(): void
    {
        $withoutAsset = PaywallConfiguration::fromArray(['enabled' => true, 'wallet_address' => '0xabc', 'currency' => 'EURC']);
        $withAsset = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xabc',
            'currency' => 'EURC',
            'asset_address' => '0x60a3E35Cc302bFA44Cb288Bc5a4F316Fdb1adb42',
            'asset_name' => 'EURC',
        ]);

        self::assertFalse($withoutAsset->isValid());
        self::assertTrue($withAsset->isValid());
        self::assertSame('EURC', $withAsset->getAssetName());
    }

    #[Test]
    public function networkAliasesResolveToCaip2IdentifiersAndUsdcDeployments(): void
    {
        $base = PaywallConfiguration::fromArray(['network' => 'base']);
        self::assertSame('eip155:8453', $base->getCaip2NetworkId());
        self::assertSame('0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', $base->getAssetAddress());
        self::assertSame('USD Coin', $base->getAssetName());
        self::assertSame('2', $base->getAssetVersion());

        $sepolia = PaywallConfiguration::fromArray(['network' => 'base-sepolia']);
        self::assertSame('eip155:84532', $sepolia->getCaip2NetworkId());
        self::assertSame('0x036CbD53842c5426634e7929541eC2318f3dCF7e', $sepolia->getAssetAddress());
        self::assertSame('USDC', $sepolia->getAssetName());
        self::assertSame('base-sepolia', $sepolia->getLegacyNetworkId());
        self::assertSame('Base Sepolia (testnet)', $sepolia->getNetworkLabel());

        $polygon = PaywallConfiguration::fromArray(['network' => 'polygon']);
        self::assertSame('eip155:137', $polygon->getCaip2NetworkId());
    }

    #[Test]
    public function caip2NetworkIsAcceptedDirectly(): void
    {
        $config = PaywallConfiguration::fromArray(['network' => 'eip155:84532']);

        self::assertSame('eip155:84532', $config->getCaip2NetworkId());
        self::assertSame('base-sepolia', $config->getLegacyNetworkId());
        self::assertSame('Base Sepolia (testnet)', $config->getNetworkLabel());

        $unknown = PaywallConfiguration::fromArray(['network' => 'solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp']);
        self::assertSame('solana:5eykt4UsFv8P8NJdTREpY1vzqKqZKvdp', $unknown->getCaip2NetworkId());
        self::assertSame('', $unknown->getAssetAddress());
    }

    #[Test]
    public function serviceMetadataIsOnlyUsedWhenTheSpecificationAllowsIt(): void
    {
        $valid = PaywallConfiguration::fromArray([
            'service_name' => 'Example Research',
            'service_tags' => ['research', 'reports'],
            'service_icon_url' => 'https://example.test/icon.png',
        ]);
        self::assertSame('Example Research', $valid->getServiceName());
        self::assertSame(['research', 'reports'], $valid->getServiceTags());
        self::assertSame('https://example.test/icon.png', $valid->getServiceIconUrl());

        $invalid = PaywallConfiguration::fromArray([
            'service_name' => str_repeat('n', 33),
            'service_tags' => ['a', 'b', 'c', 'd', 'e', 'f', "tab\t"],
            'service_icon_url' => 'ftp://example.test/icon.png',
        ]);
        self::assertSame('', $invalid->getServiceName());
        self::assertSame(['a', 'b', 'c', 'd', 'e'], $invalid->getServiceTags());
        self::assertSame('', $invalid->getServiceIconUrl());
        self::assertSame(['invalid_service_name', 'invalid_service_tags', 'invalid_service_icon_url'], array_values(array_intersect(
            $invalid->getProblems(),
            ['invalid_service_name', 'invalid_service_tags', 'invalid_service_icon_url'],
        )));
    }

    #[Test]
    public function problemsFlagMainnetPaymentsThroughTheTestnetFacilitator(): void
    {
        $testnet = PaywallConfiguration::fromArray(['enabled' => true, 'wallet_address' => '0xReceiver', 'network' => 'base-sepolia']);
        $mainnet = PaywallConfiguration::fromArray(['enabled' => true, 'wallet_address' => '0xReceiver', 'network' => 'base']);

        self::assertTrue($testnet->isTestnet());
        self::assertSame([], $testnet->getProblems());
        self::assertFalse($mainnet->isTestnet());
        self::assertSame(['testnet_facilitator'], $mainnet->getProblems());
    }

    #[Test]
    public function cdpAuthenticationNeedsCredentials(): void
    {
        $cdpHost = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xReceiver',
            'network' => 'base',
            'facilitator_url' => 'https://api.cdp.coinbase.com/platform/v2/x402',
        ]);
        self::assertSame(['cdp_without_authentication'], $cdpHost->getProblems());

        $configured = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xReceiver',
            'network' => 'base',
            'facilitator_url' => 'https://api.cdp.coinbase.com/platform/v2/x402',
            'facilitator_auth' => 'CDP',
            'facilitator_api_key_id' => 'key-id',
            'facilitator_api_key_secret' => 'secret',
        ]);
        self::assertTrue($configured->usesCdpAuthentication());
        self::assertSame('key-id', $configured->getFacilitatorApiKeyId());
        self::assertSame([], $configured->getProblems());

        self::assertContains('unknown_facilitator_auth', PaywallConfiguration::fromArray(['facilitator_auth' => 'basic'])->getProblems());
    }

    #[Test]
    public function cdpCredentialsFallBackToTheEnvironment(): void
    {
        putenv('CDP_API_KEY_ID=env-key-id');
        putenv('CDP_API_KEY_SECRET=env-secret');
        try {
            $config = PaywallConfiguration::fromArray(['facilitator_auth' => 'cdp']);

            self::assertSame('env-key-id', $config->getFacilitatorApiKeyId());
            self::assertSame('env-secret', $config->getFacilitatorApiKeySecret());
            self::assertNotContains('missing_cdp_credentials', $config->getProblems());
        } finally {
            putenv('CDP_API_KEY_ID');
            putenv('CDP_API_KEY_SECRET');
        }

        self::assertContains('missing_cdp_credentials', PaywallConfiguration::fromArray(['facilitator_auth' => 'cdp'])->getProblems());
    }

    #[Test]
    public function transactionLinksPointToTheBlockExplorerOfKnownNetworks(): void
    {
        $hash = '0x' . str_repeat('ab', 32);

        self::assertSame('https://basescan.org/tx/' . $hash, PaywallConfiguration::transactionUrl('eip155:8453', $hash));
        self::assertSame('https://sepolia.basescan.org/tx/' . $hash, PaywallConfiguration::transactionUrl('base-sepolia', $hash));
        self::assertSame('', PaywallConfiguration::transactionUrl('eip155:8453', 'not-a-hash'));
        self::assertSame('', PaywallConfiguration::transactionUrl('eip155:999', $hash));
        self::assertSame('Base', PaywallConfiguration::networkLabel('eip155:8453'));
        self::assertSame('eip155:999', PaywallConfiguration::networkLabel('eip155:999'));
    }
}
