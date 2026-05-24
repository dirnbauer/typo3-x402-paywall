<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Utility;

/**
 * Validates outbound URLs before server-side probes.
 */
final class HttpUrl
{
    public static function isAllowedOutboundHttpUrl(string $url): bool
    {
        $parts = parse_url($url);
        if (!is_array($parts)) {
            return false;
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower(trim($parts['host'] ?? '', '[]'));

        if (($scheme !== 'http' && $scheme !== 'https') || $host === '') {
            return false;
        }

        if ($host === 'localhost' || $host === 'localhost.localdomain' || str_ends_with($host, '.localhost')) {
            return false;
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return self::isPublicIp($host);
        }

        $resolvedAddresses = self::resolveHost($host);
        if ($resolvedAddresses === []) {
            return false;
        }

        foreach ($resolvedAddresses as $ipAddress) {
            if (!self::isPublicIp($ipAddress)) {
                return false;
            }
        }

        return true;
    }

    private static function isPublicIp(string $ipAddress): bool
    {
        return filter_var(
            $ipAddress,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }

    /**
     * @return string[]
     */
    private static function resolveHost(string $host): array
    {
        $addresses = [];
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                foreach (['ip', 'ipv6'] as $key) {
                    if (isset($record[$key]) && is_scalar($record[$key])) {
                        $addresses[] = (string)$record[$key];
                    }
                }
            }
        }

        if ($addresses === []) {
            $fallbackAddresses = @gethostbynamel($host);
            if (is_array($fallbackAddresses)) {
                $addresses = array_merge($addresses, $fallbackAddresses);
            }
        }

        return array_values(array_unique($addresses));
    }
}
