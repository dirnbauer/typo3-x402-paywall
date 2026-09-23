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
 * x402 v2 "PaymentRequired": the document a 402 response carries base64-encoded in PAYMENT-REQUIRED.
 */
final readonly class PaymentRequired
{
    public const int X402_VERSION = 2;

    /**
     * @param list<PaymentRequirement> $accepts Payment options the client may choose from
     * @param array<string, mixed> $extensions Protocol extension data (pass-through)
     */
    public function __construct(
        public ResourceInfo $resource,
        public array $accepts,
        public ?string $error = null,
        public array $extensions = [],
    ) {}

    /**
     * @throws \InvalidArgumentException when the value is not a base64-encoded PaymentRequired document
     */
    public static function fromHeaderValue(string $base64): self
    {
        return self::fromArray(HeaderDocument::decode($base64, 'PaymentRequired'));
    }

    /**
     * @param array<string, mixed> $data
     * @throws \InvalidArgumentException when the document offers no payment requirement
     */
    public static function fromArray(array $data): self
    {
        $accepts = [];
        foreach (is_array($data['accepts'] ?? null) ? $data['accepts'] : [] as $entry) {
            if (is_array($entry)) {
                $accepts[] = PaymentRequirement::fromArray(Json::object($entry));
            }
        }
        if ($accepts === []) {
            throw new \InvalidArgumentException('PaymentRequired contains no payment requirements', 1757600003);
        }

        $error = ScalarValue::string($data['error'] ?? null);

        return new self(
            resource: ResourceInfo::fromArray(Json::object($data['resource'] ?? null)),
            accepts: $accepts,
            error: $error !== '' ? $error : null,
            extensions: Json::object($data['extensions'] ?? null),
        );
    }

    public function withError(string $error): self
    {
        return new self($this->resource, $this->accepts, $error, $this->extensions);
    }

    public function first(): PaymentRequirement
    {
        return $this->accepts[0];
    }

    /**
     * @return array{x402Version: int, error?: string, resource: array<string, string|list<string>>, accepts: list<array<string, mixed>>, extensions?: array<string, mixed>}
     */
    public function toArray(): array
    {
        $data = ['x402Version' => self::X402_VERSION];
        if ($this->error !== null) {
            $data['error'] = $this->error;
        }
        $data['resource'] = $this->resource->toArray();
        $data['accepts'] = array_map(static fn(PaymentRequirement $requirement): array => $requirement->toArray(), $this->accepts);
        if ($this->extensions !== []) {
            $data['extensions'] = $this->extensions;
        }

        return $data;
    }

    public function toHeaderValue(): string
    {
        return base64_encode(Json::encode($this->toArray()));
    }
}
