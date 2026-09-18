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
use Webconsulting\X402Paywall\Domain\Model\SettlementResponse;
use Webconsulting\X402Paywall\Domain\Model\VerifyResponse;
use Webconsulting\X402Paywall\Utility\Json;

/**
 * Client for the x402 facilitator API (specification v2.0):
 *
 *   POST /verify  {x402Version, paymentPayload, paymentRequirements} -> VerifyResponse
 *   POST /settle  {x402Version, paymentPayload, paymentRequirements} -> SettlementResponse
 */
final class PaymentVerifier
{
    public const VERIFY_TIMEOUT = 30;
    public const SETTLE_TIMEOUT = 60;

    public function __construct(
        private readonly RequestFactory $requestFactory,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Asks the facilitator whether the payload is a valid payment for the offered requirement.
     *
     * @param array<string, mixed> $paymentRequirements Wire format of the requirement the client accepted
     */
    public function verify(PaymentPayload $payload, array $paymentRequirements, PaywallConfiguration $config): VerifyResponse
    {
        $url = $this->endpoint($config, 'verify');
        try {
            $response = VerifyResponse::fromArray($this->post($url, $payload, $paymentRequirements, self::VERIFY_TIMEOUT));
        } catch (\Throwable $exception) {
            $this->logger->error('x402: facilitator /verify failed', ['url' => $url, 'error' => $exception->getMessage()]);

            return VerifyResponse::invalid('facilitator_unreachable');
        }

        if (!$response->isValid) {
            $this->logger->warning('x402: payment verification failed', ['invalidReason' => $response->invalidReason]);
        }

        return $response;
    }

    /**
     * Executes the payment on-chain through the facilitator.
     *
     * @param array<string, mixed> $paymentRequirements
     */
    public function settle(PaymentPayload $payload, array $paymentRequirements, PaywallConfiguration $config): SettlementResponse
    {
        $url = $this->endpoint($config, 'settle');
        try {
            $response = SettlementResponse::fromArray(
                $this->post($url, $payload, $paymentRequirements, self::SETTLE_TIMEOUT),
                $config->getCaip2NetworkId(),
                $payload->getPayer(),
            );
        } catch (\Throwable $exception) {
            $this->logger->error('x402: facilitator /settle failed', ['url' => $url, 'error' => $exception->getMessage()]);

            return SettlementResponse::failed('facilitator_unreachable', $config->getCaip2NetworkId(), $payload->getPayer());
        }

        if (!$response->success) {
            $this->logger->warning('x402: settlement failed', ['errorReason' => $response->errorReason]);
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $paymentRequirements
     * @return array<string, mixed> Decoded response body
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
            'headers' => ['Accept' => 'application/json'],
            'http_errors' => false,
        ]);

        $raw = (string)$response->getBody();
        if ($raw === '') {
            return [];
        }

        try {
            return Json::decodeObject($raw);
        } catch (\JsonException) {
            return ['error' => 'invalid_facilitator_response'];
        }
    }

    private function endpoint(PaywallConfiguration $config, string $path): string
    {
        return rtrim($config->facilitatorUrl, '/') . '/' . $path;
    }
}
