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

/**
 * Shared decoding of x402 header values: base64 wrapping a JSON document.
 */
final class HeaderDocument
{
    /**
     * @return array<string, mixed>
     * @throws \InvalidArgumentException when the value is not a base64-encoded JSON document
     */
    public static function decode(string $base64, string $headerName): array
    {
        try {
            return Json::decodeObject(self::json($base64, $headerName));
        } catch (\JsonException $exception) {
            throw new \InvalidArgumentException($headerName . ' is not valid JSON', 1757600002, $exception);
        }
    }

    /**
     * The JSON text inside the header value.
     *
     * @throws \InvalidArgumentException when the value is not base64
     */
    public static function json(string $base64, string $headerName): string
    {
        $decoded = base64_decode(trim($base64), true);
        if ($decoded === false) {
            throw new \InvalidArgumentException($headerName . ' is not valid base64', 1757600001);
        }

        return $decoded;
    }
}
