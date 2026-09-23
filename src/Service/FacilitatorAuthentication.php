<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Service;

use Firebase\JWT\JWT;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;

/**
 * Request headers a facilitator needs besides the x402 documents.
 *
 * The public x402.org facilitator needs none. The Coinbase Developer Platform facilitator
 * (facilitator_auth: cdp) expects "Authorization: Bearer <JWT>" signed with a CDP secret API key:
 * a short-lived token bound to one method and URL, as the CDP SDKs issue it. Both key types are
 * supported: ECDSA (PEM, ES256) and Ed25519 (base64 of the 64-byte secret key, EdDSA).
 */
final readonly class FacilitatorAuthentication
{
    /** Lifetime of a CDP token; the CDP SDKs use the same two minutes. */
    public const int TOKEN_LIFETIME = 120;

    private const int ED25519_SECRET_KEY_LENGTH = 64;

    /**
     * @return array<string, string>
     * @throws \RuntimeException when the configured credentials cannot sign a token
     */
    public function headers(PaywallConfiguration $config, string $method, string $url): array
    {
        if (!$config->usesCdpAuthentication()) {
            return [];
        }

        return ['Authorization' => 'Bearer ' . $this->cdpToken(
            $config->getFacilitatorApiKeyId(),
            $config->getFacilitatorApiKeySecret(),
            $method,
            $url,
        )];
    }

    /**
     * @throws \RuntimeException when the credentials are missing or not a supported key
     */
    public function cdpToken(string $keyId, #[\SensitiveParameter] string $secret, string $method, string $url, ?int $now = null): string
    {
        if ($keyId === '' || $secret === '') {
            throw new \RuntimeException('The CDP facilitator needs an API key id and secret (facilitator_api_key_id / facilitator_api_key_secret or CDP_API_KEY_ID / CDP_API_KEY_SECRET)', 1757600020);
        }

        $parts = parse_url($url);
        $host = is_array($parts) ? ($parts['host'] ?? '') : '';
        if ($host === '') {
            throw new \RuntimeException('The facilitator URL has no host', 1757600021);
        }

        $now ??= time();
        $claims = [
            'sub' => $keyId,
            'iss' => 'cdp',
            'uris' => [strtoupper($method) . ' ' . $host . ($parts['path'] ?? '')],
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + self::TOKEN_LIFETIME,
        ];
        $header = ['nonce' => bin2hex(random_bytes(16))];

        // Secrets copied from environment files often carry literal "\n" instead of line breaks.
        $secret = str_replace('\n', "\n", trim($secret));
        if (str_contains($secret, '-----BEGIN')) {
            $key = openssl_pkey_get_private($secret);
            if ($key === false) {
                throw new \RuntimeException('The CDP API key secret is not a readable PEM private key', 1757600022);
            }

            return JWT::encode($claims, $key, 'ES256', $keyId, $header);
        }

        $binary = base64_decode($secret, true);
        if ($binary === false || strlen($binary) !== self::ED25519_SECRET_KEY_LENGTH) {
            throw new \RuntimeException('The CDP API key secret is neither a PEM EC key nor a base64 Ed25519 key', 1757600023);
        }

        return JWT::encode($claims, $secret, 'EdDSA', $keyId, $header);
    }
}
