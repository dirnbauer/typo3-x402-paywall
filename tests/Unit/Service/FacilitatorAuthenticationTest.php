<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Service;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Service\FacilitatorAuthentication;
use Webconsulting\X402Paywall\Utility\Json;

final class FacilitatorAuthenticationTest extends UnitTestCase
{
    #[Test]
    public function facilitatorsWithoutAuthenticationGetNoHeaders(): void
    {
        self::assertSame([], new FacilitatorAuthentication()->headers(new PaywallConfiguration(), 'POST', 'https://x402.org/facilitator/verify'));
    }

    #[Test]
    public function ecdsaKeysSignEs256TokensBoundToMethodAndUrl(): void
    {
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        self::assertNotFalse($key);
        self::assertTrue(openssl_pkey_export($key, $pem));
        self::assertIsString($pem);
        $details = openssl_pkey_get_details($key);
        self::assertIsArray($details);
        self::assertIsString($details['key']);

        // Environment files often hold the PEM with literal "\n" sequences.
        $token = new FacilitatorAuthentication()->cdpToken('organizations/o/apiKeys/k', str_replace("\n", '\n', $pem), 'post', 'https://api.cdp.coinbase.com/platform/v2/x402/settle', 1_800_000_000);

        [$header] = explode('.', $token);
        $header = Json::decodeObject(JWT::urlsafeB64Decode($header));
        self::assertSame('ES256', $header['alg']);
        self::assertSame('JWT', $header['typ']);
        self::assertSame('organizations/o/apiKeys/k', $header['kid']);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', is_string($header['nonce'] ?? null) ? $header['nonce'] : '');

        JWT::$timestamp = 1_800_000_010;
        try {
            $claims = (array)JWT::decode($token, new Key($details['key'], 'ES256'));
        } finally {
            JWT::$timestamp = null;
        }
        self::assertSame('organizations/o/apiKeys/k', $claims['sub']);
        self::assertSame('cdp', $claims['iss']);
        self::assertSame(['POST api.cdp.coinbase.com/platform/v2/x402/settle'], $claims['uris']);
        self::assertSame(1_800_000_000, $claims['nbf']);
        self::assertSame(1_800_000_120, $claims['exp']);
    }

    #[Test]
    public function missingOrUnsupportedCredentialsThrow(): void
    {
        $authentication = new FacilitatorAuthentication();
        foreach ([['', 'secret', 1757600020], ['id', 'not a key', 1757600023], ['id', "-----BEGIN EC PRIVATE KEY-----\ngarbage\n-----END EC PRIVATE KEY-----", 1757600022]] as [$id, $secret, $code]) {
            try {
                $authentication->cdpToken($id, $secret, 'POST', 'https://api.cdp.coinbase.com/platform/v2/x402/verify');
                self::fail('Expected an exception for ' . $secret);
            } catch (\RuntimeException $exception) {
                self::assertSame($code, $exception->getCode());
            }
        }
    }
}
