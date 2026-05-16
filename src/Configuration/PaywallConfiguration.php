<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Configuration;

use Webconsulting\X402Paywall\Domain\Model\PaywallConfigLike;

/**
 * Holds the x402 paywall configuration for the current site.
 */
final class PaywallConfiguration implements PaywallConfigLike
{
    public const NETWORK_BASE_MAINNET = 'base';
    public const NETWORK_BASE_SEPOLIA = 'base-sepolia';
    public const NETWORK_POLYGON = 'polygon';
    public const NETWORK_ETHEREUM = 'ethereum';

    public const PRICING_PER_REQUEST = 'per-request';
    public const PRICING_PER_PAGE = 'per-page';

    public const DEFAULT_FACILITATOR_URL = 'https://x402.org/facilitator';
    public const DEFAULT_CURRENCY = 'USDC';
    public const DEFAULT_NETWORK = 'base-sepolia';

    public function __construct(
        public readonly bool $enabled = false,
        public readonly string $walletAddress = '',
        public readonly string $network = self::DEFAULT_NETWORK,
        public readonly string $facilitatorUrl = self::DEFAULT_FACILITATOR_URL,
        public readonly string $currency = self::DEFAULT_CURRENCY,
        public readonly string $defaultPrice = '0.01',
        public readonly string $pricingMode = self::PRICING_PER_REQUEST,
        public readonly int $freePreviewParagraphs = 0,
        /** @var string[] Routes that are always free (e.g., /api/v1/health) */
        public readonly array $freeRoutes = [],
        /** @var string[] Route patterns that require payment (e.g., /api/v1/content/*) */
        public readonly array $gatedRoutePatterns = [],
        /** @var int[] Page UIDs that require payment */
        public readonly array $gatedPageUids = [],
    ) {}

    /**
     * @param array<string, mixed> $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            enabled: self::boolFromValue($config['enabled'] ?? false),
            walletAddress: self::stringFromValue($config['wallet_address'] ?? ''),
            network: self::stringFromValue($config['network'] ?? self::DEFAULT_NETWORK, self::DEFAULT_NETWORK),
            facilitatorUrl: self::stringFromValue($config['facilitator_url'] ?? self::DEFAULT_FACILITATOR_URL, self::DEFAULT_FACILITATOR_URL),
            currency: self::stringFromValue($config['currency'] ?? self::DEFAULT_CURRENCY, self::DEFAULT_CURRENCY),
            defaultPrice: self::stringFromValue($config['default_price'] ?? '0.01', '0.01'),
            pricingMode: self::stringFromValue($config['pricing_mode'] ?? self::PRICING_PER_REQUEST, self::PRICING_PER_REQUEST),
            freePreviewParagraphs: self::intFromValue($config['free_preview_paragraphs'] ?? 0),
            freeRoutes: self::stringsFromArray($config['free_routes'] ?? []),
            gatedRoutePatterns: self::stringsFromArray($config['gated_route_patterns'] ?? []),
            gatedPageUids: self::intsFromArray($config['gated_page_uids'] ?? []),
        );
    }

    public function isValid(): bool
    {
        return $this->enabled
            && $this->walletAddress !== ''
            && $this->facilitatorUrl !== '';
    }

    public function getWalletAddress(): string
    {
        return $this->walletAddress;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getNetwork(): string
    {
        return $this->network;
    }

    /**
     * Get the CAIP-2 network identifier for x402 protocol.
     */
    public function getCaip2NetworkId(): string
    {
        return match ($this->network) {
            'base' => 'eip155:8453',
            'base-sepolia' => 'eip155:84532',
            'polygon' => 'eip155:137',
            'ethereum' => 'eip155:1',
            default => $this->network,
        };
    }

    /**
     * @return string[]
     */
    private static function stringsFromArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn(mixed $item): string => self::stringFromValue($item),
            $value,
        ));
    }

    /**
     * @return int[]
     */
    private static function intsFromArray(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_map(
            static fn(mixed $item): int => self::intFromValue($item),
            $value,
        ));
    }

    private static function stringFromValue(mixed $value, string $default = ''): string
    {
        return is_scalar($value) ? (string)$value : $default;
    }

    private static function intFromValue(mixed $value): int
    {
        return is_scalar($value) ? (int)$value : 0;
    }

    private static function boolFromValue(mixed $value): bool
    {
        return is_scalar($value) ? (bool)$value : false;
    }
}
