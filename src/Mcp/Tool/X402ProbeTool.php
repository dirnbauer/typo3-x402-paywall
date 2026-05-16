<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Mcp\Tool;

use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

/**
 * MCP Tool: probe any URL for x402 payment requirements.
 *
 * Lets an AI agent check whether a URL is behind a paywall,
 * and if so, decode and return the payment requirement details.
 *
 * Example agent interaction:
 *   Agent: "Is https://example.com/news/1 behind a paywall?"
 *   Tool:  { status: 402, price: "0.01", currency: "USDC", network: "base" }
 */
final class X402ProbeTool extends AbstractMcpTool
{
    public function __construct(
        private readonly ClientInterface $httpClient,
    ) {}

    public function getName(): string
    {
        return 'x402_probe';
    }

    public function getDescription(): string
    {
        return 'Probe a URL to check if it is behind an x402 paywall. '
             . 'Returns HTTP status, and if 402, the decoded payment requirement '
             . '(price, currency, network, wallet address). '
             . 'Use this to discover what content is monetized and at what price.';
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
                'url' => [
                    'type' => 'string',
                    'description' => 'The URL to probe (must be a full URL including scheme)',
                ],
            ],
            'required' => ['url'],
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function doExecute(array $args): string
    {
        $url = (string)($args['url'] ?? '');

        if ($url === '') {
            return json_encode(['error' => 'url is required']);
        }

        try {
            $response = $this->httpClient->request('GET', $url, [
                'headers' => ['Accept' => 'application/json', 'User-Agent' => 'x402-mcp-tool/1.0'],
                'timeout' => 10,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);

            $status = $response->getStatusCode();
            $result = ['url' => $url, 'status' => $status];

            if ($status === 402) {
                $paymentHeader = $response->getHeaderLine('PAYMENT-REQUIRED')
                    ?: $response->getHeaderLine('X-PAYMENT-REQUIRED');

                if ($paymentHeader !== '') {
                    $decoded = base64_decode($paymentHeader, true);
                    if ($decoded !== false) {
                        $requirement = json_decode($decoded, true);
                        $result['paywall'] = true;
                        $result['requirement'] = $requirement;
                        $result['summary'] = sprintf(
                            'Page requires payment: %s %s on %s. Pay to: %s',
                            ($requirement['maxAmountRequired'] ?? '?'),
                            'USDC',
                            $requirement['network'] ?? '?',
                            substr((string)($requirement['payTo'] ?? ''), 0, 10) . '...',
                        );
                    }
                }

                $body = json_decode((string)$response->getBody(), true);
                if (isset($body['human_readable'])) {
                    $result['humanReadable'] = $body['human_readable'];
                }
            } elseif ($status >= 200 && $status < 300) {
                $result['paywall'] = false;
                $result['summary'] = 'URL is accessible without payment (status ' . $status . ')';
            } else {
                $result['summary'] = 'Unexpected HTTP status: ' . $status;
            }

            return json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (GuzzleException $e) {
            return json_encode(['error' => 'Request failed: ' . $e->getMessage()]);
        }
    }
}
