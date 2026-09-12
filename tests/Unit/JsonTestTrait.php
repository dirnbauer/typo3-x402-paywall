<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit;

/**
 * Typed helpers for asserting on JSON documents returned by tools and responses.
 */
trait JsonTestTrait
{
    /**
     * @return array<string, mixed>
     */
    private static function decodeJsonObject(string $json): array
    {
        $decoded = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        $result = [];
        foreach ($decoded as $key => $value) {
            $result[(string)$key] = $value;
        }

        return $result;
    }

    /**
     * Returns the value at the given key path, failing when a segment is missing.
     *
     * @param array<array-key, mixed> $data
     */
    private static function jsonPath(array $data, string|int ...$path): mixed
    {
        $current = $data;
        foreach ($path as $segment) {
            self::assertIsArray($current);
            self::assertArrayHasKey($segment, $current);
            $current = $current[$segment];
        }

        return $current;
    }
}
