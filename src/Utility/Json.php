<?php

declare(strict_types=1);

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
