<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Mcp\Tool;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Domain\Model\ResourceInfo;
use Webconsulting\X402Paywall\Mcp\Tool\X402DecodeHeaderTool;
use Webconsulting\X402Paywall\Tests\Unit\JsonTestTrait;

final class X402DecodeHeaderToolTest extends UnitTestCase
{
    use JsonTestTrait;

    #[Test]
    public function toolIsNamedX402DecodeHeader(): void
    {
        self::assertSame('x402_decode_header', (new X402DecodeHeaderTool())->getName());
    }

    #[Test]
    public function decodesAPaymentRequiredHeader(): void
    {
        $document = new PaymentRequired(
            new ResourceInfo('https://example.test/premium', 'Premium'),
            [new PaymentRequirement('exact', 'eip155:84532', '10000', '0xToken', '0xReceiver', 300, ['name' => 'USDC', 'version' => '2'])],
        );

        $result = self::decodeJsonObject((new X402DecodeHeaderTool())->execute(['header' => $document->toHeaderValue()]));

        self::assertSame($document->toArray(), $result['decoded']);
        self::assertSame('PaymentRequired', self::jsonPath($result, 'human', 'kind'));
        self::assertSame('0.01', self::jsonPath($result, 'human', 'price'));
        self::assertSame('10000', self::jsonPath($result, 'human', 'amount'));
        self::assertSame('eip155:84532', self::jsonPath($result, 'human', 'network'));
        self::assertSame('0xReceiver', self::jsonPath($result, 'human', 'pay_to'));
        self::assertSame('USDC', self::jsonPath($result, 'human', 'asset_name'));
        self::assertSame('https://example.test/premium', self::jsonPath($result, 'human', 'resource'));
    }

    #[Test]
    public function decodesPaymentPayloadAndSettlementHeaders(): void
    {
        $payload = base64_encode(json_encode([
            'x402Version' => 2,
            'accepted' => ['scheme' => 'exact', 'network' => 'eip155:8453', 'amount' => '250000', 'asset' => '0xT', 'payTo' => '0xR'],
            'payload' => ['signature' => '0x', 'authorization' => ['from' => '0xPayer', 'validBefore' => '99']],
        ], JSON_THROW_ON_ERROR));
        $settlement = base64_encode(json_encode(['success' => true, 'transaction' => '0xtx', 'network' => 'eip155:8453', 'payer' => '0xPayer'], JSON_THROW_ON_ERROR));

        $tool = new X402DecodeHeaderTool();
        $payloadResult = self::decodeJsonObject($tool->execute(['header' => $payload]));
        $settlementResult = self::decodeJsonObject($tool->execute(['header' => $settlement]));

        self::assertSame('PaymentPayload', self::jsonPath($payloadResult, 'human', 'kind'));
        self::assertSame('0xPayer', self::jsonPath($payloadResult, 'human', 'payer'));
        self::assertSame('0.25', self::jsonPath($payloadResult, 'human', 'price'));
        self::assertSame('SettlementResponse', self::jsonPath($settlementResult, 'human', 'kind'));
        self::assertTrue(self::jsonPath($settlementResult, 'human', 'success'));
        self::assertSame('0xtx', self::jsonPath($settlementResult, 'human', 'transaction'));
    }

    #[Test]
    public function reportsInvalidInput(): void
    {
        $tool = new X402DecodeHeaderTool();

        self::assertSame('{"error":"header is required"}', $tool->execute([]));
        self::assertSame('{"error":"Invalid base64 encoding"}', $tool->execute(['header' => '%%%']));
        self::assertSame('{"error":"Header does not contain JSON"}', $tool->execute(['header' => base64_encode('nope')]));
    }
}
