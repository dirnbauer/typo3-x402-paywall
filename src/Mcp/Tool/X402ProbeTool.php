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

use TYPO3\CMS\Core\Http\RequestFactory;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Http\X402Header;
use Webconsulting\X402Paywall\Legacy\X402V1;
use Webconsulting\X402Paywall\Utility\HttpUrl;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * MCP tool "x402_probe": GETs a public URL and reports whether it answers 402 Payment Required.
 * The v2 PAYMENT-REQUIRED header is decoded; without it, an x402 v1 JSON body is recognised and flagged legacy.
 */
final class X402ProbeTool extends AbstractMcpTool
{
    public const string NAME = 'x402_probe';

    private const int TIMEOUT = 10;

    public function __construct(
        private readonly RequestFactory $requestFactory,
    ) {}

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDescription(): string
    {
        return 'Probe a public URL to check whether it is behind an x402 paywall. '
            . 'Returns the HTTP status and, for 402 responses, the decoded PaymentRequired document '
            . '(x402 v2: resource, accepted payment requirements with amount in atomic units, asset, network, payTo). '
            . 'Legacy x402 v1 responses (requirements in the JSON body) are reported with "legacy": true.';
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
                    'description' => 'Absolute http(s) URL to probe. Private and loopback targets are rejected.',
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
        $url = ScalarValue::string($args['url'] ?? null);
        if ($url === '') {
            return Json::encode(['error' => 'url is required']);
        }
        if (!HttpUrl::isAllowedOutboundHttpUrl($url)) {
            return Json::encode(['error' => 'URL is not allowed for server-side probes']);
        }

        try {
            $response = $this->requestFactory->request($url, 'GET', [
                'headers' => ['Accept' => 'application/json', 'User-Agent' => 'x402-mcp-tool (TYPO3)'],
                'timeout' => self::TIMEOUT,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);
        } catch (\Throwable $exception) {
            return Json::encode(['error' => 'Request failed: ' . $exception->getMessage()]);
        }

        $status = $response->getStatusCode();
        $result = ['url' => $url, 'status' => $status];

        if ($status >= 200 && $status < 300) {
            $result['paywall'] = false;
            $result['summary'] = 'URL is accessible without payment (status ' . $status . ')';
        } elseif ($status !== 402) {
            $result['summary'] = 'Unexpected HTTP status: ' . $status;
        } else {
            $result['paywall'] = true;
            $result = [...$result, ...$this->describe402($response->getHeaderLine(X402Header::PAYMENT_REQUIRED), (string)$response->getBody())];
        }

        return Json::encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<string, mixed>
     */
    private function describe402(string $header, string $rawBody): array
    {
        try {
            $body = $rawBody === '' ? [] : Json::decodeObject($rawBody);
        } catch (\JsonException) {
            $body = [];
        }

        try {
            if ($header !== '') {
                $document = PaymentRequired::fromHeaderValue($header);
                $legacy = false;
            } elseif (ScalarValue::int($body['x402Version'] ?? null) === X402V1::VERSION) {
                $document = X402V1::paymentRequiredFromArray($body);
                $legacy = true;
            } else {
                return ['warning' => 'No PAYMENT-REQUIRED header and no x402 v1 body found'];
            }
        } catch (\InvalidArgumentException $exception) {
            return ['warning' => 'Payment requirements could not be decoded: ' . $exception->getMessage()];
        }

        $requirement = $document->first();

        return [
            'x402Version' => $legacy ? X402V1::VERSION : PaymentRequired::X402_VERSION,
            'legacy' => $legacy,
            'paymentRequired' => $legacy ? $body : $document->toArray(),
            'summary' => sprintf(
                'Payment required: %s atomic units (%s if 6 decimals) of asset %s on %s, pay to %s',
                $requirement->amount,
                PaymentRequirement::fromAtomicUnits($requirement->amount, 6),
                $requirement->asset,
                $requirement->network,
                $requirement->payTo,
            ),
        ];
    }
}
