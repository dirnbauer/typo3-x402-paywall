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

/**
 * Holds the x402 paywall configuration of one TYPO3 site (site configuration key "x402_paywall").
 *
 * Networks are addressed by a short alias ("base", "base-sepolia", "polygon", "arbitrum", "ethereum")
 * or directly by a CAIP-2 identifier ("eip155:8453"). x402 v2 always transports CAIP-2 identifiers.
 */
final class PaywallConfiguration
{
    public const SCHEME_EXACT = 'exact';

    public const DEFAULT_FACILITATOR_URL = 'https://x402.org/facilitator';
    public const DEFAULT_CURRENCY = 'USDC';
    public const DEFAULT_NETWORK = 'base-sepolia';
    public const DEFAULT_PRICE = '0.01';
    public const DEFAULT_ASSET_DECIMALS = 6;
    public const DEFAULT_MAX_TIMEOUT_SECONDS = 300;

    /**
     * Well-known networks with their native USDC deployment.
     *
     * alias => [CAIP-2 id, USDC contract, EIP-712 domain name, EIP-712 domain version, label]
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: string, 4: string}>
     */
    private const NETWORKS = [
        'base' => ['eip155:8453', '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', 'USD Coin', '2', 'Base'],
        'base-sepolia' => ['eip155:84532', '0x036CbD53842c5426634e7929541eC2318f3dCF7e', 'USDC', '2', 'Base Sepolia (testnet)'],
        'polygon' => ['eip155:137', '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359', 'USD Coin', '2', 'Polygon'],
        'arbitrum' => ['eip155:42161', '0xaf88d065e77c8cC2239327C5EDb3A432268e5831', 'USD Coin', '2', 'Arbitrum One'],
        'ethereum' => ['eip155:1', '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', 'USD Coin', '2', 'Ethereum'],
    ];

    /**
     * @param string[] $freeRoutes Route patterns that are always free (e.g. "/api/v1/health")
     * @param string[] $gatedRoutePatterns Route patterns that require payment (e.g. "/api/v1/content/*")
     * @param int[] $gatedPageUids Page UIDs that require payment without the page toggle
     */
    public function __construct(
        public readonly bool $enabled = false,
        public readonly string $walletAddress = '',
        public readonly string $network = self::DEFAULT_NETWORK,
        public readonly string $facilitatorUrl = self::DEFAULT_FACILITATOR_URL,
        public readonly string $currency = self::DEFAULT_CURRENCY,
        public readonly string $defaultPrice = self::DEFAULT_PRICE,
        public readonly string $assetAddress = '',
        public readonly int $assetDecimals = self::DEFAULT_ASSET_DECIMALS,
        public readonly string $assetName = '',
        public readonly string $assetVersion = '',
        public readonly int $maxTimeoutSeconds = self::DEFAULT_MAX_TIMEOUT_SECONDS,
        public readonly array $freeRoutes = [],
        public readonly array $gatedRoutePatterns = [],
        public readonly array $gatedPageUids = [],
        public readonly bool $legacyV1 = false,
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            enabled: self::boolFromValue($config['enabled'] ?? false),
            walletAddress: self::stringFromValue($config['wallet_address'] ?? ''),
            network: self::stringFromValue($config['network'] ?? null, self::DEFAULT_NETWORK),
            facilitatorUrl: self::stringFromValue($config['facilitator_url'] ?? null, self::DEFAULT_FACILITATOR_URL),
            currency: self::stringFromValue($config['currency'] ?? null, self::DEFAULT_CURRENCY),
            defaultPrice: self::stringFromValue($config['default_price'] ?? null, self::DEFAULT_PRICE),
            assetAddress: self::stringFromValue($config['asset_address'] ?? ''),
            assetDecimals: self::intFromValue($config['asset_decimals'] ?? null, self::DEFAULT_ASSET_DECIMALS),
            assetName: self::stringFromValue($config['asset_name'] ?? ''),
            assetVersion: self::stringFromValue($config['asset_version'] ?? ''),
            maxTimeoutSeconds: self::intFromValue($config['max_timeout_seconds'] ?? null, self::DEFAULT_MAX_TIMEOUT_SECONDS),
            freeRoutes: self::stringsFromArray($config['free_routes'] ?? []),
            gatedRoutePatterns: self::stringsFromArray($config['gated_route_patterns'] ?? []),
            gatedPageUids: self::intsFromArray($config['gated_page_uids'] ?? []),
            legacyV1: self::boolFromValue($config['legacy_v1'] ?? false),
        );
    }

    /**
     * A configuration is usable when the paywall is enabled, a receiving wallet and a facilitator are
     * set and the asset contract for the selected network is known.
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
        if (str_contains($this->network, ':')) {
            return $this->network;
        }

        return self::NETWORKS[$this->network][0] ?? $this->network;
    }

    /**
     * Network name as used by x402 v1 clients (e.g. "base-sepolia").
     */
    public function getLegacyNetworkId(): string
    {
        if (isset(self::NETWORKS[$this->network])) {
            return $this->network;
        }

        foreach (self::NETWORKS as $alias => $definition) {
            if ($definition[0] === $this->network) {
                return $alias;
            }
        }

        return $this->network;
    }

    /**
     * Token contract address used as PaymentRequirements.asset. Falls back to the native USDC deployment
     * of well-known networks when the currency is USDC and no explicit address is configured.
     */
    public function getAssetAddress(): string
    {
        if ($this->assetAddress !== '') {
            return $this->assetAddress;
        }

        if (strtoupper($this->currency) !== self::DEFAULT_CURRENCY) {
            return '';
        }

        return $this->getKnownNetwork()[1] ?? '';
    }

    /**
     * EIP-712 domain name of the asset (PaymentRequirements.extra.name).
     */
    public function getAssetName(): string
    {
        if ($this->assetName !== '') {
            return $this->assetName;
        }

        return $this->getKnownNetwork()[2] ?? $this->currency;
    }

    /**
     * EIP-712 domain version of the asset (PaymentRequirements.extra.version).
     */
    public function getAssetVersion(): string
    {
        if ($this->assetVersion !== '') {
            return $this->assetVersion;
        }

        return $this->getKnownNetwork()[3] ?? '2';
    }

    /**
     * Human-readable network label for the paywall page and the backend.
     */
    public function getNetworkLabel(): string
    {
        return $this->getKnownNetwork()[4] ?? $this->getCaip2NetworkId();
    }

    /**
     * EVM chain id derived from the CAIP-2 identifier, 0 for non-EVM networks.
     */
    public function getChainId(): int
    {
        $caip2 = $this->getCaip2NetworkId();
        if (!str_starts_with($caip2, 'eip155:')) {
            return 0;
        }

        return (int)substr($caip2, 7);
    }

    /**
     * @return array{0: string, 1: string, 2: string, 3: string, 4: string}|null
     */
    private function getKnownNetwork(): ?array
    {
        if (isset(self::NETWORKS[$this->network])) {
            return self::NETWORKS[$this->network];
        }

        foreach (self::NETWORKS as $definition) {
            if ($definition[0] === $this->network) {
                return $definition;
            }
        }

        return null;
    }

    /**
     * @return string[]
     */
    private static function stringsFromArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            $string = self::stringFromValue($item);
            if ($string !== '') {
                $result[] = $string;
            }
        }

        return $result;
    }

    /**
     * @return int[]
     */
    private static function intsFromArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $item) {
            $int = self::intFromValue($item, 0);
            if ($int > 0) {
                $result[] = $int;
            }
        }

        return $result;
    }

    private static function stringFromValue(mixed $value, string $default = ''): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $string = trim((string)$value);

        return $string !== '' ? $string : $default;
    }

    private static function intFromValue(mixed $value, int $default): int
    {
        return is_scalar($value) && $value !== '' ? (int)$value : $default;
    }

    private static function boolFromValue(mixed $value): bool
    {
        if (!is_scalar($value)) {
            return false;
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false;
    }
}
