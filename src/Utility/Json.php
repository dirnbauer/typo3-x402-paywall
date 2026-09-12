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
    /**
     * @param mixed $value
     */
    public static function encode(mixed $value, int $flags = 0): string
    {
        return json_encode($value, $flags | JSON_THROW_ON_ERROR);
    }

    /**
     * @return mixed
     */
    public static function decode(string $json): mixed
    {
        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<array-key, mixed>
     */
    public static function decodeObject(string $json): array
    {
        $decoded = self::decode($json);

        return is_array($decoded) ? $decoded : [];
    }
}
