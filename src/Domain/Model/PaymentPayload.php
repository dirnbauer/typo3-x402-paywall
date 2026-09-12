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

use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * x402 "PaymentPayload": the base64-encoded value of the PAYMENT-SIGNATURE request header
 * (v2) or of the legacy X-PAYMENT header (v1).
 */
final readonly class PaymentPayload
{
    /**
     * @param array<string, mixed> $accepted The PaymentRequirements the client chose (v2)
     * @param array<string, mixed> $payload Scheme-specific data, e.g. signature + authorization
     * @param array<string, mixed>|null $resource ResourceInfo echoed by the client (v2, optional)
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public int $x402Version,
        public array $accepted,
        public array $payload,
        public ?array $resource = null,
        public array $extensions = [],
        public string $legacyScheme = '',
        public string $legacyNetwork = '',
    ) {}

    /**
     * @throws \InvalidArgumentException when the header value cannot be decoded into a payment payload
     */
    public static function fromHeaderValue(string $base64): self
    {
        $decoded = base64_decode(trim($base64), true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('Payment header is not valid base64', 1757600010);
        }

        try {
            $data = Json::decodeObject($decoded);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('Payment header is not valid JSON', 1757600011, $exception);
        }

        return self::fromArray($data);
    }

    /**
     * @param array<array-key, mixed> $data
     * @throws \InvalidArgumentException
     */
    public static function fromArray(array $data): self
    {
        $version = ScalarValue::int($data['x402Version'] ?? null);
        $payload = $data['payload'] ?? null;
        if (!is_array($payload) || $payload === []) {
            throw new \InvalidArgumentException('Payment payload is missing scheme data', 1757600012);
        }

        $accepted = $data['accepted'] ?? [];
        $resource = $data['resource'] ?? null;
        $extensions = $data['extensions'] ?? [];

        if ($version === PaymentRequired::X402_VERSION && (!is_array($accepted) || $accepted === [])) {
            throw new \InvalidArgumentException('Payment payload does not name the accepted requirement', 1757600013);
        }

        return new self(
            x402Version: $version,
            accepted: is_array($accepted) ? self::stringKeys($accepted) : [],
            payload: self::stringKeys($payload),
            resource: is_array($resource) ? self::stringKeys($resource) : null,
            extensions: is_array($extensions) ? self::stringKeys($extensions) : [],
            legacyScheme: ScalarValue::string($data['scheme'] ?? null),
            legacyNetwork: ScalarValue::string($data['network'] ?? null),
        );
    }

    public function isLegacy(): bool
    {
        return $this->x402Version === PaymentRequired::LEGACY_X402_VERSION;
    }

    public function getScheme(): string
    {
        return ScalarValue::string($this->accepted['scheme'] ?? null, $this->legacyScheme);
    }

    public function getNetwork(): string
    {
        return ScalarValue::string($this->accepted['network'] ?? null, $this->legacyNetwork);
    }

    /**
     * Payer address as declared inside the scheme payload (EIP-3009 authorization or Permit2 authorization).
     */
    public function getPayer(): string
    {
        foreach (['authorization', 'permit2Authorization'] as $key) {
            $authorization = $this->payload[$key] ?? null;
            if (is_array($authorization)) {
                $from = ScalarValue::string($authorization['from'] ?? null);
                if ($from !== '') {
                    return $from;
                }
            }
        }

        return ScalarValue::string($this->payload['from'] ?? null);
    }

    /**
     * Wire format as sent to the facilitator.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        if ($this->isLegacy()) {
            return [
                'x402Version' => $this->x402Version,
                'scheme' => $this->getScheme(),
                'network' => $this->getNetwork(),
                'payload' => $this->payload,
            ];
        }

        $data = ['x402Version' => $this->x402Version];
        if ($this->resource !== null) {
            $data['resource'] = $this->resource;
        }
        $data['accepted'] = $this->accepted;
        $data['payload'] = $this->payload;
        if ($this->extensions !== []) {
            $data['extensions'] = $this->extensions;
        }

        return $data;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private static function stringKeys(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[(string)$key] = $value;
        }

        return $result;
    }
}
