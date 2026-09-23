<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Domain\Model;

use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * One x402 v2 "PaymentRequirements" object (an entry of PaymentRequired.accepts): scheme, CAIP-2 network,
 * amount in atomic token units, token contract address, receiving wallet, timeout and scheme-specific
 * extra data (the EIP-712 domain name/version of the token for the "exact" EVM scheme).
 */
final readonly class PaymentRequirement
{
    public const string SCHEME_EXACT = 'exact';

    /**
     * Reserved "extra" keys (specification v2, section 6.1) and the only values this extension
     * implements; a payload asking for anything else is refused.
     *
     * @var array<string, string>
     */
    private const array SUPPORTED_RESERVED_EXTRA = [
        'assetTransferMethod' => 'eip3009',
        'paymentFlow' => 'authorization',
    ];

    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public string $scheme,
        public string $network,
        public string $amount,
        public string $asset,
        public string $payTo,
        public int $maxTimeoutSeconds = PaywallConfiguration::DEFAULT_MAX_TIMEOUT_SECONDS,
        public array $extra = [],
    ) {}

    /**
     * Builds the requirement for a human-readable price ("0.01") in the configured asset.
     */
    public static function fromConfig(PaywallConfiguration $config, string $price): self
    {
        return new self(
            scheme: self::SCHEME_EXACT,
            network: $config->getCaip2NetworkId(),
            amount: self::toAtomicUnits($price, $config->assetDecimals),
            asset: $config->getAssetAddress(),
            payTo: $config->walletAddress,
            maxTimeoutSeconds: $config->maxTimeoutSeconds,
            extra: [
                'name' => $config->getAssetName(),
                'version' => $config->getAssetVersion(),
            ],
        );
    }

    /**
     * Tolerant constructor for requirement objects received from clients or other servers.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            scheme: ScalarValue::string($data['scheme'] ?? null),
            network: ScalarValue::string($data['network'] ?? null),
            amount: ScalarValue::string($data['amount'] ?? null),
            asset: ScalarValue::string($data['asset'] ?? null),
            payTo: ScalarValue::string($data['payTo'] ?? null),
            maxTimeoutSeconds: ScalarValue::int($data['maxTimeoutSeconds'] ?? null, PaywallConfiguration::DEFAULT_MAX_TIMEOUT_SECONDS),
            extra: Json::object($data['extra'] ?? null),
        );
    }

    /**
     * @return array{scheme: string, network: string, amount: string, asset: string, payTo: string, maxTimeoutSeconds: int, extra?: array<string, mixed>}
     */
    public function toArray(): array
    {
        $data = [
            'scheme' => $this->scheme,
            'network' => $this->network,
            'amount' => $this->amount,
            'asset' => $this->asset,
            'payTo' => $this->payTo,
            'maxTimeoutSeconds' => $this->maxTimeoutSeconds,
        ];
        if ($this->extra !== []) {
            $data['extra'] = $this->extra;
        }

        return $data;
    }

    /**
     * Whether a client's "accepted" requirement is exactly this requirement: every field equal
     * (addresses compare case-insensitively), every "extra" entry this server declared echoed with the
     * same value, and no transfer method or payment flow the extension does not implement.
     *
     * @param array<string, mixed> $accepted
     */
    public function matches(array $accepted): bool
    {
        $other = self::fromArray($accepted);
        if ($other->scheme !== $this->scheme
            || $other->network !== $this->network
            || $other->amount !== $this->amount
            || strcasecmp($other->asset, $this->asset) !== 0
            || strcasecmp($other->payTo, $this->payTo) !== 0
            || $other->maxTimeoutSeconds !== $this->maxTimeoutSeconds
        ) {
            return false;
        }

        foreach ($this->extra as $key => $value) {
            if (!array_key_exists($key, $other->extra) || $other->extra[$key] !== $value) {
                return false;
            }
        }

        return array_all(
            self::SUPPORTED_RESERVED_EXTRA,
            static fn(string $supported, string $key): bool => !array_key_exists($key, $other->extra) || $other->extra[$key] === $supported,
        );
    }

    /**
     * Converts a human-readable decimal amount ("0.01") to atomic token units ("10000" for 6 decimals).
     * Non-numeric input yields "0".
     */
    public static function toAtomicUnits(string $amount, int $decimals): string
    {
        $amount = str_replace(',', '.', trim($amount));
        if (preg_match('/^(\d+)(?:\.(\d+))?$/', $amount, $matches) !== 1) {
            return '0';
        }

        $fraction = substr(str_pad($matches[2] ?? '', $decimals, '0'), 0, $decimals);
        $atomic = ltrim($matches[1] . $fraction, '0');

        return $atomic !== '' ? $atomic : '0';
    }

    /**
     * Converts atomic token units back to a human-readable decimal string.
     */
    public static function fromAtomicUnits(string $atomic, int $decimals): string
    {
        if (preg_match('/^\d+$/', $atomic) !== 1) {
            return '0';
        }

        $padded = str_pad($atomic, $decimals + 1, '0', STR_PAD_LEFT);
        $integer = substr($padded, 0, -$decimals);
        $fraction = rtrim(substr($padded, -$decimals), '0');

        return $fraction === '' ? $integer : $integer . '.' . $fraction;
    }
}
