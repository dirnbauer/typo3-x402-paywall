<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Mcp\Tool;

use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * MCP tool "x402_decode_header": decodes any base64 x402 header value and explains it.
 *
 * Recognised documents: PaymentRequired (PAYMENT-REQUIRED), PaymentPayload (PAYMENT-SIGNATURE / X-PAYMENT),
 * SettlementResponse (PAYMENT-RESPONSE / X-PAYMENT-RESPONSE) and a bare PaymentRequirements object.
 */
final class X402DecodeHeaderTool extends AbstractMcpTool
{
    public const NAME = 'x402_decode_header';

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDescription(): string
    {
        return 'Decode a base64-encoded x402 header value (PAYMENT-REQUIRED, PAYMENT-SIGNATURE or PAYMENT-RESPONSE). '
            . 'Returns the decoded JSON plus a human-readable summary: document kind, price (atomic units and decimal), '
            . 'network, asset, payTo, payer and transaction hash where present.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'header' => [
                    'type' => 'string',
                    'description' => 'Raw base64-encoded header value',
                ],
                'decimals' => [
                    'type' => 'integer',
                    'minimum' => 0,
                    'maximum' => 18,
                    'default' => 6,
                    'description' => 'Token decimals used to render decimal amounts (USDC: 6)',
                ],
            ],
            'required' => ['header'],
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function doExecute(array $args): string
    {
        $header = ScalarValue::string($args['header'] ?? null);
        $decimals = max(0, min(18, ScalarValue::int($args['decimals'] ?? null, 6)));

        if ($header === '') {
            return Json::encode(['error' => 'header is required']);
        }

        $decoded = base64_decode($header, true);
        if ($decoded === false) {
            return Json::encode(['error' => 'Invalid base64 encoding']);
        }

        try {
            $document = Json::decodeObject($decoded);
        } catch (\JsonException) {
            return Json::encode(['error' => 'Header does not contain JSON']);
        }

        return Json::encode([
            'decoded' => $document,
            'human' => $this->describe($document, $decimals),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<array-key, mixed> $document
     * @return array<string, mixed>
     */
    private function describe(array $document, int $decimals): array
    {
        $version = ScalarValue::int($document['x402Version'] ?? null);

        if (isset($document['accepts']) && is_array($document['accepts'])) {
            $first = is_array($document['accepts'][0] ?? null) ? $document['accepts'][0] : [];
            $resource = is_array($document['resource'] ?? null) ? $document['resource'] : [];

            return [
                'kind' => 'PaymentRequired',
                'x402Version' => $version,
                'resource' => ScalarValue::string($resource['url'] ?? ($first['resource'] ?? null)),
                'description' => ScalarValue::string($resource['description'] ?? ($first['description'] ?? null)),
                'error' => ScalarValue::string($document['error'] ?? null),
                'options' => count($document['accepts']),
                ...$this->describeRequirement($first, $decimals),
            ];
        }

        if (isset($document['accepted']) && is_array($document['accepted'])) {
            $payload = is_array($document['payload'] ?? null) ? $document['payload'] : [];
            $authorization = is_array($payload['authorization'] ?? null) ? $payload['authorization'] : [];

            return [
                'kind' => 'PaymentPayload',
                'x402Version' => $version,
                'payer' => ScalarValue::string($authorization['from'] ?? null),
                'valid_before' => ScalarValue::string($authorization['validBefore'] ?? null),
                ...$this->describeRequirement($document['accepted'], $decimals),
            ];
        }

        if (array_key_exists('success', $document) || array_key_exists('transaction', $document)) {
            return [
                'kind' => 'SettlementResponse',
                'success' => ($document['success'] ?? null) === true,
                'transaction' => ScalarValue::string($document['transaction'] ?? null),
                'network' => ScalarValue::string($document['network'] ?? null),
                'payer' => ScalarValue::string($document['payer'] ?? null),
                'error' => ScalarValue::string($document['errorReason'] ?? null),
            ];
        }

        if (isset($document['payload']) && is_array($document['payload'])) {
            $authorization = is_array($document['payload']['authorization'] ?? null) ? $document['payload']['authorization'] : [];

            return [
                'kind' => 'PaymentPayload (x402 v1)',
                'x402Version' => $version,
                'scheme' => ScalarValue::string($document['scheme'] ?? null),
                'network' => ScalarValue::string($document['network'] ?? null),
                'payer' => ScalarValue::string($authorization['from'] ?? null),
                'amount' => ScalarValue::string($authorization['value'] ?? null),
            ];
        }

        if (isset($document['payTo'])) {
            return ['kind' => 'PaymentRequirements', ...$this->describeRequirement($document, $decimals)];
        }

        return ['kind' => 'unknown'];
    }

    /**
     * @param array<array-key, mixed> $requirement
     * @return array<string, mixed>
     */
    private function describeRequirement(array $requirement, int $decimals): array
    {
        $parsed = PaymentRequirement::fromArray($requirement);

        return [
            'scheme' => $parsed->scheme,
            'network' => $parsed->network,
            'amount' => $parsed->amount,
            'price' => PaymentRequirement::fromAtomicUnits($parsed->amount, $decimals),
            'asset' => $parsed->asset,
            'asset_name' => ScalarValue::string($parsed->extra['name'] ?? null),
            'pay_to' => $parsed->payTo,
            'timeout_seconds' => $parsed->maxTimeoutSeconds,
        ];
    }
}
