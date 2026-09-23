<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Utility;

final class Json
{
    public static function encode(mixed $value, int $flags = 0): string
    {
        return json_encode($value, $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * Decodes a JSON object into a string-keyed array; any other JSON value yields [].
     *
     * @return array<string, mixed>
     * @throws \JsonException when the input is not JSON
     */
    public static function decodeObject(string $json): array
    {
        return self::object(json_decode($json, true, flags: JSON_THROW_ON_ERROR));
    }

    /**
     * Decodes a JSON object keeping JSON objects as objects, so an empty object
     * ({}) survives a decode/encode round trip instead of turning into [].
     *
     * @throws \JsonException when the input is not JSON
     * @throws \InvalidArgumentException when the JSON value is not an object
     */
    public static function decodeObjectPreserving(string $json): \stdClass
    {
        $value = json_decode($json, false, flags: JSON_THROW_ON_ERROR);
        if (!$value instanceof \stdClass) {
            throw new \InvalidArgumentException('The JSON document is not an object', 1757600014);
        }

        return $value;
    }

    /**
     * Coerces a decoded JSON value (or any configuration value) into a string-keyed array.
     * Non-array values yield [].
     *
     * @return array<string, mixed>
     */
    public static function object(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[(string)$key] = $item;
        }

        return $result;
    }
}
