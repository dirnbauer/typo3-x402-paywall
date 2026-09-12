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

use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * x402 v2 "ResourceInfo": describes the protected resource inside PaymentRequired and PaymentPayload.
 */
final readonly class ResourceInfo
{
    public function __construct(
        public string $url,
        public string $description = '',
        public string $mimeType = '',
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: ScalarValue::string($data['url'] ?? null),
            description: ScalarValue::string($data['description'] ?? null),
            mimeType: ScalarValue::string($data['mimeType'] ?? null),
        );
    }

    /**
     * @return array{url: string, description?: string, mimeType?: string}
     */
    public function toArray(): array
    {
        $data = ['url' => $this->url];
        if ($this->description !== '') {
            $data['description'] = $this->description;
        }
        if ($this->mimeType !== '') {
            $data['mimeType'] = $this->mimeType;
        }

        return $data;
    }
}
