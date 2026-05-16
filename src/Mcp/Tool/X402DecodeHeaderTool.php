<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Mcp\Tool;

use Webconsulting\X402Paywall\Utility\Json;

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
final class X402DecodeHeaderTool extends AbstractMcpTool
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
    public function getSchema(): array
    {
        return $this->getInputSchema();
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
    protected function doExecute(array $args): string
    {
        $header = $this->stringValue($args['header'] ?? null);

        if ($header === '') {
            return Json::encode(['error' => 'header is required']);
        }

        $decoded = base64_decode($header, true);
        if ($decoded === false) {
            return Json::encode(['error' => 'Invalid base64 encoding']);
        }

        $requirement = Json::decodeObject($decoded);

        // Humanize the amount if we have asset info
        $humanAmount = null;
        $asset = $requirement['asset'] ?? [];
        $decimals = is_array($asset) ? $this->intValue($asset['decimals'] ?? null, 6) : 6;
        if (isset($requirement['maxAmountRequired'])) {
            $raw = $this->intValue($requirement['maxAmountRequired']);
            $symbol = is_array($asset) ? $this->stringValue($asset['symbol'] ?? null, 'USDC') : 'USDC';
            $humanAmount = number_format($raw / (10 ** $decimals), $decimals) . ' ' . $symbol;
        }

        return Json::encode([
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

    private function stringValue(mixed $value, string $default = ''): string
    {
        if (!is_scalar($value)) {
            return $default;
        }

        $stringValue = trim((string)$value);

        return $stringValue !== '' ? $stringValue : $default;
    }

    private function intValue(mixed $value, int $default = 0): int
    {
        return is_scalar($value) ? (int)$value : $default;
    }
}
