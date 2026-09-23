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
 * serviceName, tags and iconUrl describe the service for discovery (bazaar) listings.
 */
final readonly class ResourceInfo
{
    /** Specification v2, section 5.1.2: printable ASCII, at most 32 characters (service name and every tag). */
    public const string LABEL_PATTERN = '/^[\x20-\x7E]{1,32}$/';

    public const int MAX_TAGS = 5;
    public const int MAX_ICON_URL_LENGTH = 2048;

    /**
     * @param list<string> $tags
     */
    public function __construct(
        public string $url,
        public string $description = '',
        public string $mimeType = '',
        public string $serviceName = '',
        public array $tags = [],
        public string $iconUrl = '',
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            url: ScalarValue::string($data['url'] ?? null),
            description: ScalarValue::string($data['description'] ?? null),
            mimeType: ScalarValue::string($data['mimeType'] ?? null),
            serviceName: ScalarValue::string($data['serviceName'] ?? null),
            tags: ScalarValue::strings($data['tags'] ?? null),
            iconUrl: ScalarValue::string($data['iconUrl'] ?? null),
        );
    }

    public static function isValidLabel(string $value): bool
    {
        return preg_match(self::LABEL_PATTERN, $value) === 1;
    }

    /**
     * Absolute http(s) URL of at most 2048 characters.
     */
    public static function isValidIconUrl(string $url): bool
    {
        return strlen($url) <= self::MAX_ICON_URL_LENGTH
            && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    /**
     * @return array{url: string, description?: string, mimeType?: string, serviceName?: string, tags?: list<string>, iconUrl?: string}
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
        if ($this->serviceName !== '') {
            $data['serviceName'] = $this->serviceName;
        }
        if ($this->tags !== []) {
            $data['tags'] = $this->tags;
        }
        if ($this->iconUrl !== '') {
            $data['iconUrl'] = $this->iconUrl;
        }

        return $data;
    }
}
