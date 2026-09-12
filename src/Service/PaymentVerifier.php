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

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Domain\Model\PaymentPayload;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * Client for the x402 facilitator API (specification v2.0, 2025-12-09):
 *
 *   POST /verify    {x402Version, paymentPayload, paymentRequirements} -> {isValid, invalidReason?, payer?}
 *   POST /settle    {x402Version, paymentPayload, paymentRequirements} -> SettlementResponse
 *   GET  /supported                                                     -> {kinds, extensions, signers}
 */
final class PaymentVerifier
{
    public const VERIFY_TIMEOUT = 30;
    public const SETTLE_TIMEOUT = 60;
    public const SUPPORTED_TIMEOUT = 10;

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Asks the facilitator whether the payment payload is valid for the given requirements.
     *
     * @param array<string, mixed> $paymentRequirements Wire format of the requirement the client accepted
     * @return array{valid: true, payer: string}|array{valid: false, error: string}
     */
    public function verify(PaymentPayload $payload, array $paymentRequirements, PaywallConfiguration $config): array
    {
        $url = $this->endpoint($config, 'verify');

        try {
            [$statusCode, $body] = $this->post($url, $payload, $paymentRequirements, self::VERIFY_TIMEOUT);

            if (($body['isValid'] ?? null) === true) {
                $this->logger->info('x402: payment verified', ['facilitator' => $url]);

                return ['valid' => true, 'payer' => ScalarValue::string($body['payer'] ?? null)];
            }

            $error = ScalarValue::string(
                $body['invalidReason'] ?? ($body['error'] ?? ($body['message'] ?? null)),
                'verification_failed',
            );
            $this->logger->warning('x402: payment verification failed', [
                'status' => $statusCode,
                'invalidReason' => $error,
            ]);

            return ['valid' => false, 'error' => $error];
        } catch (\Throwable $exception) {
            $this->logger->error('x402: facilitator /verify failed', ['url' => $url, 'error' => $exception->getMessage()]);

            return ['valid' => false, 'error' => 'facilitator_unreachable'];
        }
    }

    /**
     * Executes the payment on-chain through the facilitator.
     *
     * @param array<string, mixed> $paymentRequirements
     * @return array{success: bool, transaction: string, network: string, payer: string, amount: string, errorReason: string, response: array<string, mixed>}
     */
    public function settle(PaymentPayload $payload, array $paymentRequirements, PaywallConfiguration $config): array
    {
        $url = $this->endpoint($config, 'settle');

        try {
            [$statusCode, $body] = $this->post($url, $payload, $paymentRequirements, self::SETTLE_TIMEOUT);

            $result = [
                'success' => ($body['success'] ?? null) === true,
                'transaction' => ScalarValue::string($body['transaction'] ?? null),
                'network' => ScalarValue::string($body['network'] ?? null, $config->getCaip2NetworkId()),
                'payer' => ScalarValue::string($body['payer'] ?? null),
                'amount' => ScalarValue::string($body['amount'] ?? null),
                'errorReason' => ScalarValue::string($body['errorReason'] ?? ($body['error'] ?? ($body['message'] ?? null))),
                'response' => $body,
            ];

            if ($result['success']) {
                $this->logger->info('x402: payment settled', ['transaction' => $result['transaction']]);
            } else {
                if ($result['errorReason'] === '') {
                    $result['errorReason'] = 'settlement_failed';
                }
                $this->logger->warning('x402: settlement failed', [
                    'status' => $statusCode,
                    'errorReason' => $result['errorReason'],
                ]);
            }

            return $result;
        } catch (\Throwable $exception) {
            $this->logger->error('x402: facilitator /settle failed', ['url' => $url, 'error' => $exception->getMessage()]);

            return [
                'success' => false,
                'transaction' => '',
                'network' => $config->getCaip2NetworkId(),
                'payer' => '',
                'amount' => '',
                'errorReason' => 'facilitator_unreachable',
                'response' => [],
            ];
        }
    }

    /**
     * Fetches the facilitator's supported (x402Version, scheme, network) kinds.
     *
     * @return array{kinds: list<array<string, mixed>>, extensions: list<string>, signers: array<string, mixed>}|null null when unreachable
     */
    public function supported(PaywallConfiguration $config): ?array
    {
        $url = $this->endpoint($config, 'supported');

        try {
            $response = $this->requestFactory->request($url, 'GET', [
                'timeout' => self::SUPPORTED_TIMEOUT,
                'headers' => ['Accept' => 'application/json'],
                'http_errors' => false,
            ]);

            if ($response->getStatusCode() !== 200) {
                return null;
            }

            $body = self::stringKeyArray(Json::decodeObject((string)$response->getBody()));
            $kinds = [];
            foreach (is_array($body['kinds'] ?? null) ? $body['kinds'] : [] as $kind) {
                if (is_array($kind)) {
                    $kinds[] = self::stringKeyArray($kind);
                }
            }
            $extensions = [];
            foreach (is_array($body['extensions'] ?? null) ? $body['extensions'] : [] as $extension) {
                if (is_string($extension)) {
                    $extensions[] = $extension;
                }
            }

            return [
                'kinds' => $kinds,
                'extensions' => $extensions,
                'signers' => is_array($body['signers'] ?? null) ? self::stringKeyArray($body['signers']) : [],
            ];
        } catch (\Throwable $exception) {
            $this->logger->error('x402: facilitator /supported failed', ['url' => $url, 'error' => $exception->getMessage()]);

            return null;
        }
    }

    /**
     * Whether the facilitator lists the requirement's (version, scheme, network) kind; null when unreachable.
     */
    public function supportsRequirement(PaymentRequirement $requirement, PaywallConfiguration $config, int $x402Version = 2): ?bool
    {
        $supported = $this->supported($config);
        if ($supported === null) {
            return null;
        }

        foreach ($supported['kinds'] as $kind) {
            if (
                ScalarValue::int($kind['x402Version'] ?? null) === $x402Version
                && ScalarValue::string($kind['scheme'] ?? null) === $requirement->scheme
                && ScalarValue::string($kind['network'] ?? null) === $requirement->network
            ) {
                return true;
            }
        }

        return false;
    }

    public function testConnection(PaywallConfiguration $config): bool
    {
        return $this->supported($config) !== null;
    }

    /**
     * @param array<string, mixed> $paymentRequirements
     * @return array{0: int, 1: array<string, mixed>}
     */
    private function post(string $url, PaymentPayload $payload, array $paymentRequirements, int $timeout): array
    {
        $response = $this->requestFactory->request($url, 'POST', [
            'json' => [
                'x402Version' => $payload->x402Version,
                'paymentPayload' => $payload->toArray(),
                'paymentRequirements' => $paymentRequirements,
            ],
            'timeout' => $timeout,
            'headers' => [
                'Accept' => 'application/json',
            ],
            'http_errors' => false,
        ]);

        $raw = (string)$response->getBody();
        try {
            $body = $raw === '' ? [] : self::stringKeyArray(Json::decodeObject($raw));
        } catch (\JsonException) {
            $body = ['error' => 'invalid_facilitator_response'];
        }

        return [$response->getStatusCode(), $body];
    }

    private function endpoint(PaywallConfiguration $config, string $path): string
    {
        return rtrim($config->facilitatorUrl, '/') . '/' . $path;
    }

    /**
     * @param array<array-key, mixed> $values
     * @return array<string, mixed>
     */
    private static function stringKeyArray(array $values): array
    {
        $result = [];
        foreach ($values as $key => $value) {
            $result[(string)$key] = $value;
        }

        return $result;
    }
}
