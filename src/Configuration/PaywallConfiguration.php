<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Configuration;

/**
 * Holds the x402 paywall configuration for the current site.
 */
final class PaywallConfiguration
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

    public static function fromArray(array $config): self
    {
        return new self(
            enabled: (bool)($config['enabled'] ?? false),
            walletAddress: (string)($config['wallet_address'] ?? ''),
            network: (string)($config['network'] ?? self::DEFAULT_NETWORK),
            facilitatorUrl: (string)($config['facilitator_url'] ?? self::DEFAULT_FACILITATOR_URL),
            currency: (string)($config['currency'] ?? self::DEFAULT_CURRENCY),
            defaultPrice: (string)($config['default_price'] ?? '0.01'),
            pricingMode: (string)($config['pricing_mode'] ?? self::PRICING_PER_REQUEST),
            freePreviewParagraphs: (int)($config['free_preview_paragraphs'] ?? 0),
            freeRoutes: (array)($config['free_routes'] ?? []),
            gatedRoutePatterns: (array)($config['gated_route_patterns'] ?? []),
            gatedPageUids: array_map('intval', (array)($config['gated_page_uids'] ?? [])),
        );
    }

    public function isValid(): bool
    {
        return $this->enabled
            && $this->walletAddress !== ''
            && $this->facilitatorUrl !== '';
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
}
