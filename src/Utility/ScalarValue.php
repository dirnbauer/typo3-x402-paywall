<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Utility;

final class ScalarValue
{
    public static function string(mixed $value, string $default = ''): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $value = trim((string)$value);

        return $value === '' ? $default : $value;
    }

    public static function int(mixed $value, int $default = 0): int
    {
        return is_scalar($value) ? (int)$value : $default;
    }

    public static function float(mixed $value, float $default = 0.0): float
    {
        return is_scalar($value) ? (float)$value : $default;
    }

    public static function bool(mixed $value, bool $default = false): bool
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $result = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        return $result ?? $default;
    }
}
