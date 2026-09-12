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
use Webconsulting\X402Paywall\Http\PaymentRequiredResponseFactory;
use Webconsulting\X402Paywall\Utility\HttpUrl;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * MCP tool "x402_probe": performs a GET against a public URL and reports whether it answers
 * 402 Payment Required. A v2 PAYMENT-REQUIRED header is decoded; when it is missing, a v1 style
 * JSON body ({"x402Version": 1, "accepts": [...]}) is still recognised and flagged as legacy.
 */
final class X402ProbeTool extends AbstractMcpTool
{
    public const NAME = 'x402_probe';

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
                'headers' => ['Accept' => 'application/json', 'User-Agent' => 'x402-mcp-tool/1.2'],
                'timeout' => 10,
                'allow_redirects' => false,
                'http_errors' => false,
            ]);
        } catch (\Throwable $exception) {
            return Json::encode(['error' => 'Request failed: ' . $exception->getMessage()]);
        }

        $status = $response->getStatusCode();
        $result = ['url' => $url, 'status' => $status];

        if ($status === 402) {
            $result['paywall'] = true;
            $header = $response->getHeaderLine(PaymentRequiredResponseFactory::HEADER_PAYMENT_REQUIRED);
            $body = $this->decodeBody((string)$response->getBody());

            $paymentRequired = null;
            $legacy = false;
            if ($header !== '') {
                try {
                    $paymentRequired = PaymentRequired::fromHeaderValue($header);
                } catch (\InvalidArgumentException $exception) {
                    $result['warning'] = 'PAYMENT-REQUIRED header could not be decoded: ' . $exception->getMessage();
                }
            } elseif (ScalarValue::int($body['x402Version'] ?? null) === PaymentRequired::LEGACY_X402_VERSION) {
                try {
                    $paymentRequired = PaymentRequired::fromArray($body);
                    $legacy = true;
                } catch (\InvalidArgumentException $exception) {
                    $result['warning'] = 'x402 v1 body could not be decoded: ' . $exception->getMessage();
                }
            } else {
                $result['warning'] = 'No PAYMENT-REQUIRED header and no x402 v1 body found';
            }

            if ($paymentRequired !== null) {
                $requirement = $paymentRequired->first();
                $result['x402Version'] = $legacy ? PaymentRequired::LEGACY_X402_VERSION : PaymentRequired::X402_VERSION;
                $result['legacy'] = $legacy;
                $result['paymentRequired'] = $legacy ? $body : $paymentRequired->toArray();
                $result['summary'] = sprintf(
                    'Payment required: %s atomic units (%s if 6 decimals) of asset %s on %s, pay to %s',
                    $requirement->amount,
                    PaymentRequirement::fromAtomicUnits($requirement->amount, 6),
                    $requirement->asset,
                    $requirement->network,
                    $requirement->payTo,
                );
            }
        } elseif ($status >= 200 && $status < 300) {
            $result['paywall'] = false;
            $result['summary'] = 'URL is accessible without payment (status ' . $status . ')';
        } else {
            $result['summary'] = 'Unexpected HTTP status: ' . $status;
        }

        return Json::encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decodeBody(string $body): array
    {
        if ($body === '') {
            return [];
        }

        try {
            return Json::decodeObject($body);
        } catch (\JsonException) {
            return [];
        }
    }
}
