#!/usr/bin/env php
<?php

/**
 * x402 Paywall Debug Probe
 *
 * Tests the x402 flow against a running TYPO3 instance:
 *   1. Sends a plain GET — expects a 402 + PAYMENT-REQUIRED header
 *   2. Decodes and pretty-prints the payment requirement
 *   3. Optionally sends a mock signature to test the verification path
 *
 * Usage:
 *   php tests/Debug/probe402.php https://your-typo3.local/gated-page
 *   php tests/Debug/probe402.php https://your-typo3.local/gated-page --with-mock-signature
 */

declare(strict_types=1);

$url = $argv[1] ?? null;
$withMock = in_array('--with-mock-signature', $argv, true);

if ($url === null) {
    fwrite(STDERR, "Usage: php probe402.php <url> [--with-mock-signature]\n");
    exit(1);
}

echo "\n=== x402 Paywall Debug Probe ===\n";
echo "URL: $url\n\n";

// --- Step 1: plain GET ---
echo "→ Step 1: Plain GET request\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HTTPHEADER     => ['Accept: application/json'],
    CURLOPT_TIMEOUT        => 10,
]);

$raw = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$rawHeaders = substr($raw, 0, $headerSize);
$body = substr($raw, $headerSize);

echo "  HTTP status: $httpCode\n";

if ($httpCode !== 402) {
    echo "  ⚠  Expected 402, got $httpCode\n";
    echo "  Is the paywall enabled on this page/route?\n";
    echo "  Response body:\n" . substr($body, 0, 500) . "\n";
    exit(1);
}

echo "  ✓ Got 402 Payment Required\n\n";

// Parse headers
$paymentRequiredHeader = null;
foreach (explode("\r\n", $rawHeaders) as $line) {
    if (stripos($line, 'PAYMENT-REQUIRED:') === 0 || stripos($line, 'X-PAYMENT-REQUIRED:') === 0) {
        [, $value] = explode(':', $line, 2);
        $paymentRequiredHeader = trim($value);
        break;
    }
}

if ($paymentRequiredHeader === null) {
    echo "  ✗ No PAYMENT-REQUIRED header found\n";
    echo "  Raw response headers:\n$rawHeaders\n";
    exit(1);
}

echo "→ Step 2: Decode PAYMENT-REQUIRED header\n";

$decoded = base64_decode($paymentRequiredHeader, true);
if ($decoded === false) {
    echo "  ✗ Header is not valid base64\n";
    exit(1);
}

$requirement = json_decode($decoded, true);
if (!is_array($requirement)) {
    echo "  ✗ Header is not valid JSON after base64 decode\n";
    echo "  Raw: $paymentRequiredHeader\n";
    exit(1);
}

echo "  ✓ Payment requirement decoded:\n";
echo json_encode($requirement, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

// Also show body
$bodyJson = json_decode($body, true);
if (is_array($bodyJson)) {
    echo "→ Response body (human-readable):\n";
    if (isset($bodyJson['human_readable'])) {
        foreach ($bodyJson['human_readable'] as $k => $v) {
            echo "  $k: $v\n";
        }
    }
    echo "\n";
}

if (!$withMock) {
    echo "→ To test with a mock payment signature, run:\n";
    echo "  php probe402.php $url --with-mock-signature\n\n";
    echo "→ For real end-to-end testing, see: tests/Debug/README.md\n";
    exit(0);
}

// --- Step 3: retry with a mock signature (will fail verification, but tests the path) ---
echo "→ Step 3: Retry with mock PAYMENT-SIGNATURE header\n";
echo "  (This will fail verification — it tests that the middleware reads the header\n";
echo "   and calls the facilitator, returning a 402 with an error)\n\n";

$mockSignature = base64_encode(json_encode([
    'from'      => '0x0000000000000000000000000000000000000001',
    'signature' => '0x' . str_repeat('ab', 65),
    'network'   => $requirement['network'] ?? 'eip155:84532',
    'payload'   => $paymentRequiredHeader,
]));

$ch2 = curl_init($url);
curl_setopt_array($ch2, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HEADER         => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_HTTPHEADER     => [
        'Accept: application/json',
        'PAYMENT-SIGNATURE: ' . $mockSignature,
    ],
    CURLOPT_TIMEOUT => 15,
]);

$raw2 = curl_exec($ch2);
$headerSize2 = curl_getinfo($ch2, CURLINFO_HEADER_SIZE);
$httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
curl_close($ch2);

$body2 = substr($raw2, $headerSize2);
$bodyJson2 = json_decode($body2, true);

echo "  HTTP status: $httpCode2\n";

if ($httpCode2 === 402 && isset($bodyJson2['error'])) {
    echo "  ✓ Middleware reached facilitator and got rejection:\n";
    echo "    error: " . $bodyJson2['error'] . "\n";
    echo "\n  This confirms the middleware is wired correctly.\n";
    echo "  Use a real signed payment to get a 200.\n";
} elseif ($httpCode2 === 200) {
    echo "  ✓ Got 200 — payment accepted (mock signature worked, facilitator may be in permissive mode)\n";
} else {
    echo "  Unexpected status $httpCode2\n";
    echo "  Body: " . substr($body2, 0, 500) . "\n";
}

echo "\n=== Done ===\n";
