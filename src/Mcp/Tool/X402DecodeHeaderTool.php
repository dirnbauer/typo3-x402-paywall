<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Mcp\Tool;

/**
 * MCP Tool: decode a raw PAYMENT-REQUIRED header value.
 *
 * When an agent receives a 402 response, it can use this tool to
 * decode the base64 PAYMENT-REQUIRED header and understand what
 * payment is required (price, currency, network, wallet).
 *
 * Example agent interaction:
 *   Agent: "Decode this header: eyJzY2hlbWUi..."
 *   Tool:  { scheme: "exact", network: "base-sepolia", price: "10000", payTo: "0x..." }
 */
final class X402DecodeHeaderTool
{
    public function getName(): string
    {
        return 'x402_decode_header';
    }

    public function getDescription(): string
    {
        return 'Decode a base64-encoded PAYMENT-REQUIRED header from an x402 402 response. '
             . 'Pass the raw header value and get back the payment requirement details: '
             . 'scheme, network, amount in base units, payTo wallet address, asset info. '
             . 'Useful when you have a raw 402 response and want to understand what payment is needed.';
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
                    'description' => 'The raw base64-encoded PAYMENT-REQUIRED header value',
                ],
            ],
            'required' => ['header'],
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    public function execute(array $args): string
    {
        $header = trim((string)($args['header'] ?? ''));

        if ($header === '') {
            return json_encode(['error' => 'header is required']);
        }

        $decoded = base64_decode($header, true);
        if ($decoded === false) {
            return json_encode(['error' => 'Invalid base64 encoding']);
        }

        $requirement = json_decode($decoded, true);
        if (!is_array($requirement)) {
            return json_encode(['error' => 'Decoded value is not valid JSON']);
        }

        // Humanize the amount if we have asset info
        $humanAmount = null;
        $decimals = (int)($requirement['asset']['decimals'] ?? 6);
        if (isset($requirement['maxAmountRequired'])) {
            $raw = (int)$requirement['maxAmountRequired'];
            $humanAmount = number_format($raw / (10 ** $decimals), $decimals) . ' ' . ($requirement['asset']['symbol'] ?? 'USDC');
        }

        return json_encode([
            'decoded' => $requirement,
            'human' => [
                'price' => $humanAmount,
                'network' => $requirement['network'] ?? null,
                'pay_to' => $requirement['payTo'] ?? null,
                'resource' => $requirement['resource'] ?? null,
                'description' => $requirement['description'] ?? null,
                'timeout_seconds' => $requirement['maxTimeoutSeconds'] ?? null,
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
