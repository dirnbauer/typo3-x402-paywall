<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Domain\Model\PaymentPayload;

final class PaymentPayloadTest extends UnitTestCase
{
    /**
     * @return array<string, mixed>
     */
    private static function v2Payload(): array
    {
        return [
            'x402Version' => 2,
            'resource' => ['url' => 'https://example.test/premium'],
            'accepted' => [
                'scheme' => 'exact',
                'network' => 'eip155:84532',
                'amount' => '10000',
                'asset' => '0xToken',
                'payTo' => '0xReceiver',
                'maxTimeoutSeconds' => 300,
            ],
            'payload' => [
                'signature' => '0xsig',
                'authorization' => [
                    'from' => '0xPayer',
                    'to' => '0xReceiver',
                    'value' => '10000',
                    'validAfter' => '1',
                    'validBefore' => '2',
                    'nonce' => '0x00',
                ],
            ],
        ];
    }

    #[Test]
    public function decodesAV2PaymentSignatureHeader(): void
    {
        $payload = PaymentPayload::fromHeaderValue(base64_encode(json_encode(self::v2Payload(), JSON_THROW_ON_ERROR)));

        self::assertSame(2, $payload->x402Version);
        self::assertSame('exact', $payload->getScheme());
        self::assertSame('eip155:84532', $payload->getNetwork());
        self::assertSame('0xPayer', $payload->getPayer());
        self::assertSame(self::v2Payload(), $payload->toArray());
    }

    #[Test]
    public function decodesAV1XPaymentHeader(): void
    {
        $data = [
            'x402Version' => 1,
            'scheme' => 'exact',
            'network' => 'base-sepolia',
            'payload' => ['signature' => '0xsig', 'authorization' => ['from' => '0xPayer']],
        ];

        $payload = PaymentPayload::fromHeaderValue(base64_encode(json_encode($data, JSON_THROW_ON_ERROR)));

        self::assertSame(1, $payload->x402Version);
        self::assertSame(['scheme' => 'exact', 'network' => 'base-sepolia'], $payload->accepted);
        self::assertSame('exact', $payload->getScheme());
        self::assertSame('base-sepolia', $payload->getNetwork());
        self::assertSame($data, $payload->toArray());
    }

    #[Test]
    public function payerFallsBackToPermit2Authorization(): void
    {
        $data = self::v2Payload();
        $data['payload'] = ['signature' => '0xsig', 'permit2Authorization' => ['from' => '0xPermitPayer']];

        self::assertSame('0xPermitPayer', PaymentPayload::fromArray($data)->getPayer());
    }

    #[Test]
    public function rejectsInvalidBase64(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentPayload::fromHeaderValue('%%%');
    }

    #[Test]
    public function rejectsPayloadsWithoutSchemeData(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentPayload::fromHeaderValue(base64_encode('{"x402Version":2,"accepted":{"scheme":"exact"}}'));
    }

    #[Test]
    public function keepsUnknownFieldsForTheFacilitator(): void
    {
        $data = self::v2Payload();
        $data['extensions'] = ['bazaar' => true];
        $data['payload']['assetTransferMethod'] = 'eip3009';

        self::assertSame($data, PaymentPayload::fromArray($data)->toArray());
    }

    #[Test]
    public function rejectsV1PayloadsWithoutSchemeAndNetwork(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentPayload::fromArray(['x402Version' => 1, 'payload' => ['signature' => '0x']]);
    }

    #[Test]
    public function rejectsV2PayloadsWithoutAcceptedRequirement(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentPayload::fromHeaderValue(base64_encode('{"x402Version":2,"payload":{"signature":"0x"}}'));
    }
}
