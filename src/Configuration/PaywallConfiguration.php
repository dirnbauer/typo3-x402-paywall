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

use Webconsulting\X402Paywall\Domain\Model\ResourceInfo;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * The x402 paywall configuration of one TYPO3 site (site configuration key "x402_paywall").
 *
 * Networks are addressed by a short alias ("base", "base-sepolia", "polygon", "arbitrum", "ethereum")
 * or directly by a CAIP-2 identifier ("eip155:8453"). x402 v2 always transports CAIP-2 identifiers.
 */
final readonly class PaywallConfiguration
{
    public const string DEFAULT_FACILITATOR_URL = 'https://x402.org/facilitator';
    public const string DEFAULT_CURRENCY = 'USDC';
    public const string DEFAULT_NETWORK = 'base-sepolia';
    public const string DEFAULT_PRICE = '0.01';
    public const int DEFAULT_ASSET_DECIMALS = 6;
    public const int DEFAULT_MAX_TIMEOUT_SECONDS = 300;

    /** facilitator_auth value for the Coinbase Developer Platform facilitator (JWT per request). */
    public const string AUTH_CDP = 'cdp';

    /** Environment variables the CDP SDKs read; used when the site configuration names no key. */
    public const string ENV_CDP_API_KEY_ID = 'CDP_API_KEY_ID';
    public const string ENV_CDP_API_KEY_SECRET = 'CDP_API_KEY_SECRET';

    private const string CDP_FACILITATOR_HOST = 'api.cdp.coinbase.com';

    /** The public facilitator of x402.org settles on testnets only. */
    private const string PUBLIC_TESTNET_FACILITATOR_HOST = 'x402.org';

    /**
     * Well-known EVM networks with their native USDC deployment and its EIP-712 domain.
     *
     * @var array<string, array{caip2: string, usdc: string, name: string, version: string, label: string, testnet: bool, explorer: string}>
     */
    private const array NETWORKS = [
        'base' => ['caip2' => 'eip155:8453', 'usdc' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913', 'name' => 'USD Coin', 'version' => '2', 'label' => 'Base', 'testnet' => false, 'explorer' => 'https://basescan.org/tx/'],
        'base-sepolia' => ['caip2' => 'eip155:84532', 'usdc' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e', 'name' => 'USDC', 'version' => '2', 'label' => 'Base Sepolia (testnet)', 'testnet' => true, 'explorer' => 'https://sepolia.basescan.org/tx/'],
        'polygon' => ['caip2' => 'eip155:137', 'usdc' => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359', 'name' => 'USD Coin', 'version' => '2', 'label' => 'Polygon', 'testnet' => false, 'explorer' => 'https://polygonscan.com/tx/'],
        'arbitrum' => ['caip2' => 'eip155:42161', 'usdc' => '0xaf88d065e77c8cC2239327C5EDb3A432268e5831', 'name' => 'USD Coin', 'version' => '2', 'label' => 'Arbitrum One', 'testnet' => false, 'explorer' => 'https://arbiscan.io/tx/'],
        'ethereum' => ['caip2' => 'eip155:1', 'usdc' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48', 'name' => 'USD Coin', 'version' => '2', 'label' => 'Ethereum', 'testnet' => false, 'explorer' => 'https://etherscan.io/tx/'],
    ];

    /**
     * @param list<string> $freeRoutes Route patterns that are always free (e.g. "/api/v1/health")
     * @param list<string> $gatedRoutePatterns Route patterns that require payment (e.g. "/api/v1/content/*")
     * @param list<int> $gatedPageUids Page UIDs that require payment without the page toggle
     * @param list<string> $serviceTags ResourceInfo.tags for discovery listings
     * @param string $facilitatorAuth "" (none) or "cdp"
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
        public string $serviceName = '',
        public array $serviceTags = [],
        public string $serviceIconUrl = '',
        public string $facilitatorAuth = '',
        public string $facilitatorApiKeyId = '',
        #[\SensitiveParameter]
        public string $facilitatorApiKeySecret = '',
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
            serviceName: ScalarValue::string($config['service_name'] ?? null),
            serviceTags: ScalarValue::strings($config['service_tags'] ?? null),
            serviceIconUrl: ScalarValue::string($config['service_icon_url'] ?? null),
            facilitatorAuth: strtolower(ScalarValue::string($config['facilitator_auth'] ?? null)),
            facilitatorApiKeyId: ScalarValue::string($config['facilitator_api_key_id'] ?? null),
            facilitatorApiKeySecret: ScalarValue::string($config['facilitator_api_key_secret'] ?? null),
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
        return array_find_key(
            self::NETWORKS,
            fn(array $definition, string $alias): bool => $alias === $this->network || $definition['caip2'] === $this->network,
        ) ?? $this->network;
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
        return self::networkLabel($this->network);
    }

    /**
     * Label of a network given by alias or CAIP-2 identifier; unknown networks keep their identifier.
     */
    public static function networkLabel(string $network): string
    {
        return self::networkDefinition($network)['label'] ?? $network;
    }

    /**
     * Block explorer URL of a transaction on a well-known network, '' otherwise.
     */
    public static function transactionUrl(string $network, string $transaction): string
    {
        $explorer = self::networkDefinition($network)['explorer'] ?? '';

        return $explorer !== '' && preg_match('/^0x[0-9a-fA-F]{64}$/', $transaction) === 1 ? $explorer . $transaction : '';
    }

    public function isTestnet(): bool
    {
        return $this->knownNetwork()['testnet'] ?? false;
    }

    /**
     * ResourceInfo.serviceName; '' when not configured or not printable ASCII of at most 32 characters.
     */
    public function getServiceName(): string
    {
        return ResourceInfo::isValidLabel($this->serviceName) ? $this->serviceName : '';
    }

    /**
     * ResourceInfo.tags: the valid configured tags, at most five.
     *
     * @return list<string>
     */
    public function getServiceTags(): array
    {
        return array_slice(array_values(array_filter($this->serviceTags, ResourceInfo::isValidLabel(...))), 0, ResourceInfo::MAX_TAGS);
    }

    /**
     * ResourceInfo.iconUrl; '' when not configured or not an absolute http(s) URL.
     */
    public function getServiceIconUrl(): string
    {
        return ResourceInfo::isValidIconUrl($this->serviceIconUrl) ? $this->serviceIconUrl : '';
    }

    public function usesCdpAuthentication(): bool
    {
        return $this->facilitatorAuth === self::AUTH_CDP;
    }

    /**
     * CDP API key id: site configuration, then the CDP_API_KEY_ID environment variable.
     */
    public function getFacilitatorApiKeyId(): string
    {
        return $this->facilitatorApiKeyId !== '' ? $this->facilitatorApiKeyId : ScalarValue::string(getenv(self::ENV_CDP_API_KEY_ID));
    }

    /**
     * CDP API key secret: site configuration, then the CDP_API_KEY_SECRET environment variable.
     */
    public function getFacilitatorApiKeySecret(): string
    {
        return $this->facilitatorApiKeySecret !== '' ? $this->facilitatorApiKeySecret : ScalarValue::string(getenv(self::ENV_CDP_API_KEY_SECRET));
    }

    /**
     * Configuration mistakes the backend module reports, as label keys (problem.<key>).
     *
     * @return list<string>
     */
    public function getProblems(): array
    {
        $problems = [];
        if ($this->walletAddress === '') {
            $problems[] = 'missing_wallet';
        }
        if ($this->getAssetAddress() === '') {
            $problems[] = 'unknown_asset';
        }
        $facilitatorHost = strtolower((string)parse_url($this->facilitatorUrl, PHP_URL_HOST));
        if ($facilitatorHost === self::PUBLIC_TESTNET_FACILITATOR_HOST && !$this->isTestnet()) {
            $problems[] = 'testnet_facilitator';
        }
        if ($facilitatorHost === self::CDP_FACILITATOR_HOST && !$this->usesCdpAuthentication()) {
            $problems[] = 'cdp_without_authentication';
        }
        if ($this->usesCdpAuthentication() && ($this->getFacilitatorApiKeyId() === '' || $this->getFacilitatorApiKeySecret() === '')) {
            $problems[] = 'missing_cdp_credentials';
        }
        if ($this->facilitatorAuth !== '' && !$this->usesCdpAuthentication()) {
            $problems[] = 'unknown_facilitator_auth';
        }
        if ($this->serviceName !== '' && $this->getServiceName() === '') {
            $problems[] = 'invalid_service_name';
        }
        if (count($this->getServiceTags()) !== count($this->serviceTags)) {
            $problems[] = 'invalid_service_tags';
        }
        if ($this->serviceIconUrl !== '' && $this->getServiceIconUrl() === '') {
            $problems[] = 'invalid_service_icon_url';
        }

        return $problems;
    }

    /**
     * @return array{caip2: string, usdc: string, name: string, version: string, label: string, testnet: bool, explorer: string}|null
     */
    private function knownNetwork(): ?array
    {
        return self::networkDefinition($this->network);
    }

    /**
     * @return array{caip2: string, usdc: string, name: string, version: string, label: string, testnet: bool, explorer: string}|null
     */
    private static function networkDefinition(string $network): ?array
    {
        return array_find(
            self::NETWORKS,
            static fn(array $definition, string $alias): bool => $alias === $network || $definition['caip2'] === $network,
        );
    }
}
