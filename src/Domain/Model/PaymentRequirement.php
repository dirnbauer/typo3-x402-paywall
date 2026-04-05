<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Domain\Model;

/**
 * Represents the x402 payment requirement returned in a 402 response.
 * Follows the x402 V2 specification.
 */
final class PaymentRequirement
{
    public function __construct(
        public readonly string $scheme = 'exact',
        public readonly string $network = '',
        public readonly string $maxAmountRequired = '',
        public readonly string $resource = '',
        public readonly string $description = '',
        public readonly string $mimeType = '',
        public readonly int $maxTimeoutSeconds = 300,
        public readonly string $payTo = '',
        /** @var array{address: string, symbol: string, decimals: int} */
        public readonly array $asset = [],
        public readonly ?int $outputLength = null,
    ) {}

    public static function fromConfig(
        PaywallConfigLike $config,
        string $requestUri,
        string $price,
        string $contentDescription = '',
    ): self {
        return new self(
            scheme: 'exact',
            network: $config->getCaip2NetworkId(),
            maxAmountRequired: self::toBaseUnits($price, 6), // USDC = 6 decimals
            resource: $requestUri,
            description: $contentDescription ?: "Access to $requestUri",
            mimeType: 'application/json',
            maxTimeoutSeconds: 300,
            payTo: $config->getWalletAddress(),
            asset: self::getAssetForCurrency($config->getCurrency(), $config->getNetwork()),
        );
    }

    /**
     * Encode as base64 JSON for the PAYMENT-REQUIRED header.
     */
    public function toHeaderValue(): string
    {
        return base64_encode(json_encode($this->toArray(), JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'scheme' => $this->scheme,
            'network' => $this->network,
            'maxAmountRequired' => $this->maxAmountRequired,
            'resource' => $this->resource,
            'description' => $this->description,
            'maxTimeoutSeconds' => $this->maxTimeoutSeconds,
            'payTo' => $this->payTo,
            'asset' => $this->asset,
        ];

        if ($this->outputLength !== null) {
            $data['outputLength'] = $this->outputLength;
        }

        return $data;
    }

    /**
     * Convert a human-readable price to base units (e.g., "0.01" USDC = "10000").
     */
    private static function toBaseUnits(string $amount, int $decimals): string
    {
        $parts = explode('.', $amount, 2);
        $integer = $parts[0];
        $fraction = str_pad($parts[1] ?? '', $decimals, '0');
        $fraction = substr($fraction, 0, $decimals);

        return ltrim($integer . $fraction, '0') ?: '0';
    }

    /**
     * Get the asset descriptor for a currency on a given network.
     *
     * @return array{address: string, symbol: string, decimals: int}
     */
    private static function getAssetForCurrency(string $currency, string $network): array
    {
        // USDC contract addresses per network
        $usdcAddresses = [
            'base' => '0x833589fCD6eDb6E08f4c7C32D4f71b54bdA02913',
            'base-sepolia' => '0x036CbD53842c5426634e7929541eC2318f3dCF7e',
            'polygon' => '0x3c499c542cEF5E3811e1192ce70d8cC03d5c3359',
            'ethereum' => '0xA0b86991c6218b36c1d19D4a2e9Eb0cE3606eB48',
        ];

        return match (strtoupper($currency)) {
            'USDC' => [
                'address' => $usdcAddresses[$network] ?? $usdcAddresses['base-sepolia'],
                'symbol' => 'USDC',
                'decimals' => 6,
            ],
            default => [
                'address' => '',
                'symbol' => $currency,
                'decimals' => 18,
            ],
        };
    }
}

/**
 * Interface so PaymentRequirement can work with both PaywallConfiguration and test doubles.
 */
interface PaywallConfigLike
{
    public function getCaip2NetworkId(): string;
    public function getWalletAddress(): string;
    public function getCurrency(): string;
    public function getNetwork(): string;
}
