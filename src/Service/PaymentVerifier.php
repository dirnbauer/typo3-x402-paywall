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

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Http\RequestFactory;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Domain\Model\PaymentPayload;
use Webconsulting\X402Paywall\Domain\Model\SettlementResponse;
use Webconsulting\X402Paywall\Domain\Model\VerifyResponse;
use Webconsulting\X402Paywall\Utility\Json;

/**
 * Client for the x402 facilitator API (specification v2):
 *
 *   POST /verify    {x402Version, paymentPayload, paymentRequirements} -> VerifyResponse
 *   POST /settle    {x402Version, paymentPayload, paymentRequirements} -> SettlementResponse
 *   GET  /supported                                                    -> {kinds, extensions, signers}
 *
 * The payment payload is forwarded exactly as the client sent it. A facilitator answer with a
 * VerifyResponse/SettlementResponse body counts whatever its HTTP status (the CDP facilitator reports
 * settlement_pending with status 500). A settlement_pending answer is retried once, like the
 * reference servers do; the facilitator then reconciles against the transaction it already broadcast.
 */
final readonly class PaymentVerifier
{
    public const int VERIFY_TIMEOUT = 30;
    public const int SETTLE_TIMEOUT = 60;
    public const int SUPPORTED_TIMEOUT = 10;
    public const int CONNECT_TIMEOUT = 10;

    public function __construct(
        private RequestFactory $requestFactory,
        private LoggerInterface $logger,
        private FacilitatorAuthentication $authentication = new FacilitatorAuthentication(),
        private int $settleTimeout = self::SETTLE_TIMEOUT,
    ) {}

    /**
     * Asks the facilitator whether the payload is a valid payment for the offered requirement.
     *
     * @param array<string, mixed> $paymentRequirements Wire format of the requirement the client accepted
     */
    public function verify(PaymentPayload $payload, array $paymentRequirements, PaywallConfiguration $config): VerifyResponse
    {
        $url = self::endpoint($config, 'verify');
        try {
            $body = $this->post($url, $payload, $paymentRequirements, $config, self::VERIFY_TIMEOUT);
        } catch (\Throwable $exception) {
            $this->logger->error('x402: facilitator /verify failed', ['url' => $url, 'error' => $exception->getMessage()]);

            return VerifyResponse::invalid(VerifyResponse::UNEXPECTED_VERIFY_ERROR, 'The facilitator could not be reached.');
        }

        $response = array_key_exists('isValid', $body)
            ? VerifyResponse::fromArray($body)
            : VerifyResponse::invalid(VerifyResponse::UNEXPECTED_VERIFY_ERROR, VerifyResponse::fromArray($body)->invalidMessage);
        if (!$response->isValid) {
            $this->logger->warning('x402: payment verification failed', ['invalidReason' => $response->invalidReason, 'invalidMessage' => $response->invalidMessage]);
        }

        return $response;
    }

    /**
     * Executes the payment on chain through the facilitator.
     *
     * @param array<string, mixed> $paymentRequirements
     */
    public function settle(PaymentPayload $payload, array $paymentRequirements, PaywallConfiguration $config): SettlementResponse
    {
        $settlement = $this->settleOnce($payload, $paymentRequirements, $config);
        if ($settlement->isSettlementPending()) {
            $this->logger->notice('x402: settlement pending, asking the facilitator once more', ['transaction' => $settlement->transaction]);
            $retry = $this->settleOnce($payload, $paymentRequirements, $config);
            // A retry that got no answer at all must not hide the transaction hash of the first attempt.
            $settlement = $retry->outcomeUnknown ? $settlement : $retry;
        }

        if (!$settlement->success) {
            $this->logger->warning('x402: settlement not completed', [
                'errorReason' => $settlement->errorReason,
                'errorMessage' => $settlement->errorMessage,
                'transaction' => $settlement->transaction,
            ]);
        }

        return $settlement;
    }

    /**
     * GET /supported: the payment kinds (x402Version, scheme, network), extensions and signers of the facilitator.
     *
     * @return array<string, mixed>
     * @throws \RuntimeException when the facilitator does not answer with a supported-kinds document
     */
    public function supported(PaywallConfiguration $config): array
    {
        $url = self::endpoint($config, 'supported');
        $response = $this->requestFactory->request($url, 'GET', [
            'headers' => ['Accept' => 'application/json'] + $this->authentication->headers($config, 'GET', $url),
            'timeout' => self::SUPPORTED_TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'http_errors' => false,
        ]);

        try {
            $body = Json::decodeObject((string)$response->getBody());
        } catch (\JsonException) {
            $body = [];
        }
        if ($response->getStatusCode() !== 200 || !is_array($body['kinds'] ?? null)) {
            throw new \RuntimeException(sprintf('The facilitator answered GET /supported with HTTP %d', $response->getStatusCode()), 1757600030);
        }

        return $body;
    }

    /**
     * @param array<string, mixed> $paymentRequirements
     */
    private function settleOnce(PaymentPayload $payload, array $paymentRequirements, PaywallConfiguration $config): SettlementResponse
    {
        $url = self::endpoint($config, 'settle');
        $network = $config->getCaip2NetworkId();
        $payer = $payload->getPayer();
        $started = hrtime(true);
        try {
            $body = $this->post($url, $payload, $paymentRequirements, $config, $this->settleTimeout);
        } catch (\Throwable $exception) {
            $this->logger->error('x402: facilitator /settle failed', ['url' => $url, 'error' => $exception->getMessage()]);
            $seconds = (hrtime(true) - $started) / 1e9;

            return self::requestMayHaveArrived($exception, $seconds, $this->settleTimeout)
                ? SettlementResponse::outcomeUnknown($network, $payer, 'The facilitator did not answer; the payment may still settle.')
                : SettlementResponse::failed(SettlementResponse::UNEXPECTED_SETTLE_ERROR, $network, $payer, 'The facilitator could not be reached.');
        }

        if (!array_key_exists('success', $body)) {
            return SettlementResponse::failed(
                SettlementResponse::UNEXPECTED_SETTLE_ERROR,
                $network,
                $payer,
                SettlementResponse::fromArray($body)->errorMessage,
                $body,
            );
        }

        return SettlementResponse::fromArray($body, $network, $payer);
    }

    /**
     * Whether a failed settle request may have reached the facilitator, so that a transaction may
     * have been broadcast. Failures while connecting (name resolution, refused connection, TLS)
     * happen before the request is sent. Guzzle 7 also reports a response timeout as ConnectException,
     * which is why a connection error that took the whole timeout counts as a request that arrived.
     */
    private static function requestMayHaveArrived(\Throwable $exception, float $seconds, int $timeout): bool
    {
        if ($exception instanceof ConnectException) {
            return $seconds >= $timeout - 1;
        }

        // Anything thrown before the transfer (e.g. signing the CDP token) never reached the facilitator.
        return $exception instanceof ClientExceptionInterface || $exception instanceof GuzzleException;
    }

    /**
     * @param array<string, mixed> $paymentRequirements
     * @return array<string, mixed> Decoded response body; [] for an empty or non-JSON body
     */
    private function post(string $url, PaymentPayload $payload, array $paymentRequirements, PaywallConfiguration $config, int $timeout): array
    {
        $headers = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ] + $this->authentication->headers($config, 'POST', $url);

        $response = $this->requestFactory->request($url, 'POST', [
            'body' => Json::encode([
                'x402Version' => $payload->x402Version,
                'paymentPayload' => $payload->toWire(),
                'paymentRequirements' => $paymentRequirements,
            ], JSON_UNESCAPED_SLASHES),
            'headers' => $headers,
            'timeout' => $timeout,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'http_errors' => false,
        ]);

        $raw = (string)$response->getBody();
        try {
            $body = $raw === '' ? [] : Json::decodeObject($raw);
        } catch (\JsonException) {
            $body = [];
        }
        if ($body === [] && $response->getStatusCode() >= 300) {
            return ['message' => sprintf('HTTP %d from the facilitator', $response->getStatusCode())];
        }

        return $body;
    }

    private static function endpoint(PaywallConfiguration $config, string $path): string
    {
        return rtrim($config->facilitatorUrl, '/') . '/' . $path;
    }
}
