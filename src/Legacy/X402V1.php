<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Legacy;

use Webconsulting\X402Paywall\Domain\Model\PaymentPayload;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Domain\Model\ResourceInfo;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * x402 v1 compatibility (specification v1, HTTP transport v1), active only with the legacy_v1 site setting.
 *
 * Everything that differs from v2 lives here: the X-PAYMENT / X-PAYMENT-RESPONSE headers, alias network
 * names ("base-sepolia" instead of "eip155:84532"), scheme and network at the top level of the
 * PaymentPayload, and the "PaymentRequirementsResponse" 402 body whose requirements carry
 * maxAmountRequired plus the resource fields inline. The public facilitator still advertises
 * x402Version 1 kinds, which is why the dialect is kept.
 */
final class X402V1
{
    public const int VERSION = 1;
    public const string HEADER_PAYMENT = 'X-PAYMENT';
    public const string HEADER_PAYMENT_RESPONSE = 'X-PAYMENT-RESPONSE';

    /**
     * v1 402 body ("PaymentRequirementsResponse") for a v2 document.
     *
     * @return array{x402Version: int, error: string, accepts: list<array<string, mixed>>}
     */
    public static function paymentRequiredToArray(PaymentRequired $document, string $network): array
    {
        return [
            'x402Version' => self::VERSION,
            'error' => $document->error ?? 'Payment required',
            'accepts' => array_map(
                static fn(PaymentRequirement $requirement): array => self::requirementToArray($requirement, $document->resource, $network),
                $document->accepts,
            ),
        ];
    }

    /**
     * v1 "PaymentRequirements": the amount is named maxAmountRequired and the resource fields are inline.
     *
     * @return array<string, mixed>
     */
    public static function requirementToArray(PaymentRequirement $requirement, ResourceInfo $resource, string $network): array
    {
        return [
            'scheme' => $requirement->scheme,
            'network' => $network,
            'maxAmountRequired' => $requirement->amount,
            'resource' => $resource->url,
            'description' => $resource->description,
            'mimeType' => $resource->mimeType,
            'payTo' => $requirement->payTo,
            'maxTimeoutSeconds' => $requirement->maxTimeoutSeconds,
            'asset' => $requirement->asset,
            'extra' => $requirement->extra !== [] ? $requirement->extra : null,
        ];
    }

    /**
     * Parses a v1 402 body into the v2 model; the resource is taken from the first requirement.
     *
     * @param array<string, mixed> $body
     * @throws \InvalidArgumentException when the body offers no payment requirement
     */
    public static function paymentRequiredFromArray(array $body): PaymentRequired
    {
        $accepts = [];
        $first = [];
        foreach (is_array($body['accepts'] ?? null) ? $body['accepts'] : [] as $entry) {
            if (!is_array($entry)) {
                continue;
            }
            $entry = Json::object($entry);
            if ($accepts === []) {
                $first = $entry;
            }
            $accepts[] = self::requirementFromArray($entry);
        }
        if ($accepts === []) {
            throw new \InvalidArgumentException('PaymentRequired contains no payment requirements', 1757600003);
        }

        $error = ScalarValue::string($body['error'] ?? null);

        return new PaymentRequired(
            resource: new ResourceInfo(
                url: ScalarValue::string($first['resource'] ?? null),
                description: ScalarValue::string($first['description'] ?? null),
                mimeType: ScalarValue::string($first['mimeType'] ?? null),
            ),
            accepts: $accepts,
            error: $error !== '' ? $error : null,
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function requirementFromArray(array $data): PaymentRequirement
    {
        return PaymentRequirement::fromArray([...$data, 'amount' => $data['maxAmountRequired'] ?? null]);
    }

    /**
     * A v1 PaymentPayload names scheme and network at the top level instead of an "accepted" object.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    public static function acceptedFromPayload(array $data): array
    {
        $accepted = [];
        foreach (['scheme', 'network'] as $key) {
            $value = ScalarValue::string($data[$key] ?? null);
            if ($value !== '') {
                $accepted[$key] = $value;
            }
        }

        return $accepted;
    }

    /**
     * Whether a v1 payload refers to the offered requirement (v1 payloads carry neither amount nor asset).
     */
    public static function payloadMatches(PaymentPayload $payload, PaymentRequirement $requirement, string $network): bool
    {
        return $payload->getScheme() === $requirement->scheme && $payload->getNetwork() === $network;
    }
}
