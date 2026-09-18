<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Configuration;

use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * The x402 paywall configuration of one TYPO3 site (site configuration key "x402_paywall").
 *
 * Networks are addressed by a short alias ("base", "base-sepolia", "polygon", "arbitrum", "ethereum")
 * or directly by a CAIP-2 identifier ("eip155:8453"). x402 v2 always transports CAIP-2 identifiers.
 */
final readonly class PaywallConfiguration
{
    public const DEFAULT_FACILITATOR_URL = 'https://x402.org/facilitator';
    public const DEFAULT_CURRENCY = 'USDC';
    public const DEFAULT_NETWORK = 'base-sepolia';
    public const DEFAULT_PRICE = '0.01';
    public const DEFAULT_ASSET_DECIMALS = 6;
    public const DEFAULT_MAX_TIMEOUT_SECONDS = 300;

    /**
     * Well-known EVM networks with their native USDC deployment and its EIP-712 domain.
     *
     * @var array<string, array{caip2: string, usdc: string, name: string, version: string, label: string}>
     */
    private const NETWORKS = [
        'base' => ['caip2' => 'eip155:8453', 'usdc' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', 'name' => 'USD Coin', 'version' => '2', 'label' => 'Base'],
        'base-sepolia' => ['caip2' => 'eip155:84532', 'usdc' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e', 'name' => 'USDC', 'version' => '2', 'label' => 'Base Sepolia (testnet)'],
        'polygon' => ['caip2' => 'eip155:137', 'usdc' => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359', 'name' => 'USD Coin', 'version' => '2', 'label' => 'Polygon'],
        'arbitrum' => ['caip2' => 'eip155:42161', 'usdc' => '0xaf88d065e77c8cC2239327C5EDb3A432268e5831', 'name' => 'USD Coin', 'version' => '2', 'label' => 'Arbitrum One'],
        'ethereum' => ['caip2' => 'eip155:1', 'usdc' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', 'name' => 'USD Coin', 'version' => '2', 'label' => 'Ethereum'],
    ];

    /**
     * @param list<string> $freeRoutes Route patterns that are always free (e.g. "/api/v1/health")
     * @param list<string> $gatedRoutePatterns Route patterns that require payment (e.g. "/api/v1/content/*")
     * @param list<int> $gatedPageUids Page UIDs that require payment without the page toggle
     */
    public function __construct(
        public bool $enabled = false,
        public string $walletAddress = '',
        public string $network = self::DEFAULT_NETWORK,
        public string $facilitatorUrl = self::DEFAULT_FACILITATOR_URL,
        public string $currency = self::DEFAULT_CURRENCY,
        public string $defaultPrice = self::DEFAULT_PRICE,
        public string $assetAddress = '',
        public int $assetDecimals = self::DEFAULT_ASSET_DECIMALS,
        public string $assetName = '',
        public string $assetVersion = '',
        public int $maxTimeoutSeconds = self::DEFAULT_MAX_TIMEOUT_SECONDS,
        public array $freeRoutes = [],
        public array $gatedRoutePatterns = [],
        public array $gatedPageUids = [],
        public bool $legacyV1 = false,
    ) {}

    /**
     * @param array<string, mixed> $config The "x402_paywall" block of a site configuration
     */
    public static function fromArray(array $config): self
    {
        return new self(
            enabled: ScalarValue::bool($config['enabled'] ?? null),
            walletAddress: ScalarValue::string($config['wallet_address'] ?? null),
            network: ScalarValue::string($config['network'] ?? null, self::DEFAULT_NETWORK),
            facilitatorUrl: ScalarValue::string($config['facilitator_url'] ?? null, self::DEFAULT_FACILITATOR_URL),
            currency: ScalarValue::string($config['currency'] ?? null, self::DEFAULT_CURRENCY),
            defaultPrice: ScalarValue::string($config['default_price'] ?? null, self::DEFAULT_PRICE),
            assetAddress: ScalarValue::string($config['asset_address'] ?? null),
            assetDecimals: ScalarValue::int($config['asset_decimals'] ?? null, self::DEFAULT_ASSET_DECIMALS),
            assetName: ScalarValue::string($config['asset_name'] ?? null),
            assetVersion: ScalarValue::string($config['asset_version'] ?? null),
            maxTimeoutSeconds: ScalarValue::int($config['max_timeout_seconds'] ?? null, self::DEFAULT_MAX_TIMEOUT_SECONDS),
            freeRoutes: ScalarValue::strings($config['free_routes'] ?? null),
            gatedRoutePatterns: ScalarValue::strings($config['gated_route_patterns'] ?? null),
            gatedPageUids: ScalarValue::positiveInts($config['gated_page_uids'] ?? null),
            legacyV1: ScalarValue::bool($config['legacy_v1'] ?? null),
        );
    }

    /**
     * Usable when enabled, a receiving wallet and a facilitator are set and the asset contract is known.
     */
    public function isValid(): bool
    {
        return $this->enabled
            && $this->walletAddress !== ''
            && $this->facilitatorUrl !== ''
            && $this->getAssetAddress() !== '';
    }

    /**
     * CAIP-2 network identifier as transported in x402 v2 (e.g. "eip155:8453").
     */
    public function getCaip2NetworkId(): string
    {
        return str_contains($this->network, ':') ? $this->network : ($this->knownNetwork()['caip2'] ?? $this->network);
    }

    /**
     * Network alias as used by x402 v1 clients (e.g. "base-sepolia").
     */
    public function getLegacyNetworkId(): string
    {
        foreach (self::NETWORKS as $alias => $definition) {
            if ($alias === $this->network || $definition['caip2'] === $this->network) {
                return $alias;
            }
        }

        return $this->network;
    }

    /**
     * Token contract address (PaymentRequirements.asset). Falls back to the native USDC deployment of
     * well-known networks when the currency is USDC and no explicit address is configured.
     */
    public function getAssetAddress(): string
    {
        if ($this->assetAddress !== '' || strtoupper($this->currency) !== self::DEFAULT_CURRENCY) {
            return $this->assetAddress;
        }

        return $this->knownNetwork()['usdc'] ?? '';
    }

    /**
     * EIP-712 domain name of the token (PaymentRequirements.extra.name).
     */
    public function getAssetName(): string
    {
        return $this->assetName !== '' ? $this->assetName : ($this->knownNetwork()['name'] ?? $this->currency);
    }

    /**
     * EIP-712 domain version of the token (PaymentRequirements.extra.version).
     */
    public function getAssetVersion(): string
    {
        return $this->assetVersion !== '' ? $this->assetVersion : ($this->knownNetwork()['version'] ?? '2');
    }

    /**
     * Human-readable network label for the paywall page.
     */
    public function getNetworkLabel(): string
    {
        return $this->knownNetwork()['label'] ?? $this->getCaip2NetworkId();
    }

    /**
     * @return array{caip2: string, usdc: string, name: string, version: string, label: string}|null
     */
    private function knownNetwork(): ?array
    {
        foreach (self::NETWORKS as $alias => $definition) {
            if ($alias === $this->network || $definition['caip2'] === $this->network) {
                return $definition;
            }
        }

        return null;
    }
}
