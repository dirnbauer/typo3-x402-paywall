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
 * x402 v2 "PaymentRequired" document: the base64-encoded value of the PAYMENT-REQUIRED response header.
 */
final readonly class PaymentRequired
{
    public const X402_VERSION = 2;
    public const LEGACY_X402_VERSION = 1;

    /**
     * @param list<PaymentRequirement> $accepts
     */
    public function __construct(
        public ResourceInfo $resource,
        public array $accepts,
        public ?string $error = null,
    ) {}

    /**
     * Decodes a PAYMENT-REQUIRED header value.
     *
     * @throws \InvalidArgumentException when the value is not a base64-encoded PaymentRequired document
     */
    public static function fromHeaderValue(string $base64): self
    {
        $decoded = base64_decode(trim($base64), true);
        if ($decoded === false) {
            throw new \InvalidArgumentException('PAYMENT-REQUIRED is not valid base64', 1757600001);
        }

        try {
            $data = Json::decodeObject($decoded);
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException('PAYMENT-REQUIRED is not valid JSON', 1757600002, $exception);
        }

        return self::fromArray($data);
    }

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $rawAccepts = $data['accepts'] ?? null;
        $accepts = [];
        $firstRawAccept = [];
        if (is_array($rawAccepts)) {
            foreach ($rawAccepts as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                if ($accepts === []) {
                    $firstRawAccept = $entry;
                }
                $accepts[] = PaymentRequirement::fromArray($entry);
            }
        }

        if ($accepts === []) {
            throw new \InvalidArgumentException('PaymentRequired contains no payment requirements', 1757600003);
        }

        $rawResource = $data['resource'] ?? null;
        $resource = is_array($rawResource)
            ? ResourceInfo::fromArray($rawResource)
            // x402 v1 carried resource and description inside each requirement
            : new ResourceInfo(
                url: ScalarValue::string($firstRawAccept['resource'] ?? null),
                description: ScalarValue::string($firstRawAccept['description'] ?? null),
                mimeType: ScalarValue::string($firstRawAccept['mimeType'] ?? null),
            );

        $error = $data['error'] ?? null;

        return new self($resource, $accepts, is_string($error) && $error !== '' ? $error : null);
    }

    public function withError(string $error): self
    {
        return new self($this->resource, $this->accepts, $error);
    }

    public function first(): PaymentRequirement
    {
        return $this->accepts[0];
    }

    /**
     * @return array{x402Version: int, error?: string, resource: array<string, string>, accepts: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        $data = ['x402Version' => self::X402_VERSION];
        if ($this->error !== null) {
            $data['error'] = $this->error;
        }
        $data['resource'] = $this->resource->toArray();
        $data['accepts'] = array_map(static fn(PaymentRequirement $requirement): array => $requirement->toArray(), $this->accepts);

        return $data;
    }

    /**
     * x402 v1 "PaymentRequirementsResponse" (the 402 body v1 clients parse).
     *
     * @return array{x402Version: int, error: string, accepts: list<array<string, mixed>>}
     */
    public function toLegacyArray(string $legacyNetwork): array
    {
        return [
            'x402Version' => self::LEGACY_X402_VERSION,
            'error' => $this->error ?? 'Payment required',
            'accepts' => array_map(
                fn(PaymentRequirement $requirement): array => $requirement->toLegacyArray($this->resource, $legacyNetwork),
                $this->accepts,
            ),
        ];
    }

    public function toHeaderValue(): string
    {
        return base64_encode(Json::encode($this->toArray()));
    }
}
