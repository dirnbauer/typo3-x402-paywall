<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Service;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * Verifies x402 payment signatures by calling the facilitator's /verify endpoint.
 * Optionally settles payments via the /settle endpoint.
 */
final class PaymentVerifier
{
    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Verify a payment signature against the facilitator.
     *
     * @return array{valid: true, details: array<string, mixed>}|array{valid: false, error: string}
     */
    public function verify(
        string $paymentSignatureBase64,
        string $paymentRequirementBase64,
        PaywallConfiguration $config,
    ): array {
        $facilitatorUrl = rtrim($config->facilitatorUrl, '/');
        $verifyUrl = $facilitatorUrl . '/verify';

        try {
            $response = $this->requestFactory->request($verifyUrl, 'POST', [
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
            $body = self::stringKeyArray(Json::decodeObject((string)$response->getBody()));

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
                'error' => ScalarValue::string($body['error'] ?? ($body['message'] ?? null), 'Verification failed'),
            ];
        } catch (\Throwable $e) {
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
     * @return array{settled: true, txHash: string}|array{settled: false, error: string}
     */
    public function settle(
        string $paymentSignatureBase64,
        string $paymentRequirementBase64,
        PaywallConfiguration $config,
    ): array {
        $facilitatorUrl = rtrim($config->facilitatorUrl, '/');
        $settleUrl = $facilitatorUrl . '/settle';

        try {
            $response = $this->requestFactory->request($settleUrl, 'POST', [
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

            $body = self::stringKeyArray(Json::decodeObject((string)$response->getBody()));

            if ($response->getStatusCode() === 200 && ($body['settled'] ?? false) === true) {
                $txHash = ScalarValue::string($body['txHash'] ?? null);
                $this->logger->info('x402 payment settled', [
                    'txHash' => $txHash !== '' ? $txHash : 'unknown',
                ]);
                return [
                    'settled' => true,
                    'txHash' => $txHash,
                ];
            }

            return [
                'settled' => false,
                'error' => ScalarValue::string($body['error'] ?? ($body['message'] ?? null), 'Settlement failed'),
            ];
        } catch (\Throwable $e) {
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
            $response = $this->requestFactory->request(rtrim($config->facilitatorUrl, '/'), 'GET', [
                'timeout' => 10,
            ]);
            return $response->getStatusCode() < 500;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private static function stringKeyArray(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            if (is_string($key)) {
                $result[$key] = $value;
            }
        }

        return $result;
    }
}
