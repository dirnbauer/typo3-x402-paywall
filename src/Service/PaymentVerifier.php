<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Service;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Log\LoggerInterface;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;

/**
 * Verifies x402 payment signatures by calling the facilitator's /verify endpoint.
 * Optionally settles payments via the /settle endpoint.
 */
final class PaymentVerifier
{
    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Verify a payment signature against the facilitator.
     *
     * @return array{valid: bool, error?: string, details?: array<string, mixed>}
     */
    public function verify(
        string $paymentSignatureBase64,
        string $paymentRequirementBase64,
        PaywallConfiguration $config,
    ): array {
        $facilitatorUrl = rtrim($config->facilitatorUrl, '/');
        $verifyUrl = $facilitatorUrl . '/verify';

        try {
            $response = $this->httpClient->request('POST', $verifyUrl, [
                'json' => [
                    'paymentPayload' => $paymentSignatureBase64,
                    'paymentRequirements' => $paymentRequirementBase64,
                ],
                'timeout' => 30,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            $statusCode = $response->getStatusCode();
            $body = json_decode((string)$response->getBody(), true);

            if ($statusCode === 200 && ($body['valid'] ?? false) === true) {
                $this->logger->info('x402 payment verified successfully', [
                    'facilitator' => $verifyUrl,
                ]);
                return ['valid' => true, 'details' => $body];
            }

            $this->logger->warning('x402 payment verification failed', [
                'status' => $statusCode,
                'response' => $body,
            ]);

            return [
                'valid' => false,
                'error' => $body['error'] ?? $body['message'] ?? 'Verification failed',
            ];
        } catch (GuzzleException $e) {
            $this->logger->error('x402 facilitator communication error', [
                'url' => $verifyUrl,
                'error' => $e->getMessage(),
            ]);

            return [
                'valid' => false,
                'error' => 'Facilitator unreachable: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Settle a verified payment via the facilitator.
     *
     * @return array{settled: bool, txHash?: string, error?: string}
     */
    public function settle(
        string $paymentSignatureBase64,
        string $paymentRequirementBase64,
        PaywallConfiguration $config,
    ): array {
        $facilitatorUrl = rtrim($config->facilitatorUrl, '/');
        $settleUrl = $facilitatorUrl . '/settle';

        try {
            $response = $this->httpClient->request('POST', $settleUrl, [
                'json' => [
                    'paymentPayload' => $paymentSignatureBase64,
                    'paymentRequirements' => $paymentRequirementBase64,
                ],
                'timeout' => 60,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
            ]);

            $body = json_decode((string)$response->getBody(), true);

            if ($response->getStatusCode() === 200) {
                $this->logger->info('x402 payment settled', [
                    'txHash' => $body['txHash'] ?? 'unknown',
                ]);
                return [
                    'settled' => true,
                    'txHash' => $body['txHash'] ?? null,
                ];
            }

            return [
                'settled' => false,
                'error' => $body['error'] ?? 'Settlement failed',
            ];
        } catch (GuzzleException $e) {
            $this->logger->error('x402 settlement error', [
                'error' => $e->getMessage(),
            ]);

            return [
                'settled' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Test connectivity to the facilitator.
     */
    public function testConnection(PaywallConfiguration $config): bool
    {
        try {
            $response = $this->httpClient->request('GET', rtrim($config->facilitatorUrl, '/'), [
                'timeout' => 10,
            ]);
            return $response->getStatusCode() < 500;
        } catch (GuzzleException) {
            return false;
        }
    }
}
