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

use Webconsulting\X402Paywall\Domain\Model\PaymentPayload;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Domain\Model\SettlementResponse;
use Webconsulting\X402Paywall\Legacy\X402V1;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * MCP tool "x402_decode_header": decodes a base64 x402 header value and explains it.
 *
 * Recognised documents: PaymentRequired (PAYMENT-REQUIRED, v2 header or v1 body), PaymentPayload
 * (PAYMENT-SIGNATURE / X-PAYMENT), SettlementResponse (PAYMENT-RESPONSE) and a bare PaymentRequirements object.
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
            'human' => self::describe($document, $decimals),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private static function describe(array $document, int $decimals): array
    {
        $version = ScalarValue::int($document['x402Version'] ?? null);

        if (isset($document['accepts'])) {
            $required = $version === X402V1::VERSION
                ? X402V1::paymentRequiredFromArray($document)
                : PaymentRequired::fromArray($document);

            return [
                'kind' => 'PaymentRequired',
                'x402Version' => $version,
                'resource' => $required->resource->url,
                'description' => $required->resource->description,
                'error' => $required->error ?? '',
                'options' => count($required->accepts),
                ...self::describeRequirement($required->first(), $decimals),
            ];
        }

        if (isset($document['payload'])) {
            $payload = PaymentPayload::fromArray($document);
            $authorization = Json::object($payload->payload['authorization'] ?? null);

            return [
                'kind' => 'PaymentPayload',
                'x402Version' => $version,
                'payer' => $payload->getPayer(),
                'valid_before' => ScalarValue::string($authorization['validBefore'] ?? null),
                ...self::describeRequirement(PaymentRequirement::fromArray($payload->accepted), $decimals),
            ];
        }

        if (array_key_exists('success', $document) || array_key_exists('transaction', $document)) {
            $settlement = SettlementResponse::fromArray($document);

            return [
                'kind' => 'SettlementResponse',
                'success' => $settlement->success,
                'transaction' => $settlement->transaction,
                'network' => $settlement->network,
                'payer' => $settlement->payer,
                'error' => $settlement->errorReason,
            ];
        }

        if (isset($document['payTo'])) {
            return ['kind' => 'PaymentRequirements', ...self::describeRequirement(PaymentRequirement::fromArray($document), $decimals)];
        }

        return ['kind' => 'unknown'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function describeRequirement(PaymentRequirement $requirement, int $decimals): array
    {
        return [
            'scheme' => $requirement->scheme,
            'network' => $requirement->network,
            'amount' => $requirement->amount,
            'price' => PaymentRequirement::fromAtomicUnits($requirement->amount, $decimals),
            'asset' => $requirement->asset,
            'asset_name' => ScalarValue::string($requirement->extra['name'] ?? null),
            'pay_to' => $requirement->payTo,
            'timeout_seconds' => $requirement->maxTimeoutSeconds,
        ];
    }
}
