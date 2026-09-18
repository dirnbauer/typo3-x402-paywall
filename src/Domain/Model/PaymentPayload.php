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

use Webconsulting\X402Paywall\Legacy\X402V1;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * x402 "PaymentPayload": the document a client sends base64-encoded in PAYMENT-SIGNATURE (v2) or
 * X-PAYMENT (v1). The decoded document is kept verbatim and forwarded unchanged to the facilitator.
 */
final readonly class PaymentPayload
{
    /**
     * @param array<string, mixed> $document Decoded header document
     * @param array<string, mixed> $accepted PaymentRequirements the client chose (x402 v1: scheme and network only)
     * @param array<string, mixed> $payload Scheme-specific data, e.g. signature and EIP-3009 authorization
     */
    private function __construct(
        private array $document,
        public int $x402Version,
        public array $accepted,
        public array $payload,
    ) {}

    /**
     * @throws \InvalidArgumentException when the value is not a base64-encoded PaymentPayload document
     */
    public static function fromHeaderValue(string $base64): self
    {
        return self::fromArray(HeaderDocument::decode($base64, 'PaymentPayload'));
    }

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException when scheme data or the accepted requirement is missing
     */
    public static function fromArray(array $data): self
    {
        $version = ScalarValue::int($data['x402Version'] ?? null);
        $payload = Json::object($data['payload'] ?? null);
        if ($payload === []) {
            throw new \InvalidArgumentException('PaymentPayload is missing scheme data', 1757600012);
        }

        $accepted = $version === X402V1::VERSION
            ? X402V1::acceptedFromPayload($data)
            : Json::object($data['accepted'] ?? null);
        if ($accepted === []) {
            throw new \InvalidArgumentException('PaymentPayload does not name the accepted requirement', 1757600013);
        }

        return new self($data, $version, $accepted, $payload);
    }

    public function getScheme(): string
    {
        return ScalarValue::string($this->accepted['scheme'] ?? null);
    }

    public function getNetwork(): string
    {
        return ScalarValue::string($this->accepted['network'] ?? null);
    }

    /**
     * Payer address as declared inside the scheme payload (EIP-3009 or Permit2 authorization).
     */
    public function getPayer(): string
    {
        foreach (['authorization', 'permit2Authorization'] as $key) {
            $from = ScalarValue::string(Json::object($this->payload[$key] ?? null)['from'] ?? null);
            if ($from !== '') {
                return $from;
            }
        }

        return ScalarValue::string($this->payload['from'] ?? null);
    }

    /**
     * The document as received, for the facilitator.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->document;
    }
}
