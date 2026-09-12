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
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Domain\Model\ResourceInfo;

final class PaymentRequiredTest extends UnitTestCase
{
    #[Test]
    public function toArrayFollowsTheV2Schema(): void
    {
        $document = new PaymentRequired(
            new ResourceInfo('https://example.test/premium', 'Premium article', 'text/html'),
            [new PaymentRequirement('exact', 'eip155:84532', '10000', '0xToken', '0xReceiver', 300, ['name' => 'USDC', 'version' => '2'])],
        );

        $array = $document->toArray();

        self::assertSame(2, $array['x402Version']);
        self::assertArrayNotHasKey('error', $array);
        self::assertSame(['url' => 'https://example.test/premium', 'description' => 'Premium article', 'mimeType' => 'text/html'], $array['resource']);
        self::assertCount(1, $array['accepts']);
        self::assertSame('10000', $array['accepts'][0]['amount']);
        self::assertSame(['x402Version', 'resource', 'accepts'], array_keys($array));
    }

    #[Test]
    public function headerValueRoundTrips(): void
    {
        $document = new PaymentRequired(
            new ResourceInfo('https://example.test/premium'),
            [new PaymentRequirement('exact', 'eip155:8453', '10000', '0xToken', '0xReceiver')],
            'insufficient_funds',
        );

        $decoded = PaymentRequired::fromHeaderValue($document->toHeaderValue());

        self::assertSame($document->toArray(), $decoded->toArray());
        self::assertSame('insufficient_funds', $decoded->error);
        self::assertSame('0xReceiver', $decoded->first()->payTo);
    }

    #[Test]
    public function withErrorAddsTheErrorField(): void
    {
        $document = new PaymentRequired(new ResourceInfo('https://example.test/'), [new PaymentRequirement('exact', 'eip155:1', '1', '0xT', '0xR')]);

        self::assertSame('invalid_payload', $document->withError('invalid_payload')->toArray()['error'] ?? null);
        self::assertNull($document->error);
    }

    #[Test]
    public function toLegacyArrayProducesTheV1Body(): void
    {
        $document = new PaymentRequired(
            new ResourceInfo('https://example.test/premium', 'Premium'),
            [new PaymentRequirement('exact', 'eip155:84532', '10000', '0xToken', '0xReceiver')],
        );

        $legacy = $document->toLegacyArray('base-sepolia');

        self::assertSame(1, $legacy['x402Version']);
        self::assertSame('Payment required', $legacy['error']);
        self::assertSame('base-sepolia', $legacy['accepts'][0]['network']);
        self::assertSame('https://example.test/premium', $legacy['accepts'][0]['resource']);
    }

    #[Test]
    public function fromArrayAcceptsV1DocumentsWithoutResourceInfo(): void
    {
        $document = PaymentRequired::fromArray([
            'x402Version' => 1,
            'error' => 'Payment required',
            'accepts' => [[
                'scheme' => 'exact',
                'network' => 'base-sepolia',
                'maxAmountRequired' => '10000',
                'resource' => 'https://example.test/premium',
                'description' => 'Premium',
                'payTo' => '0xReceiver',
                'asset' => '0xToken',
            ]],
        ]);

        self::assertSame('https://example.test/premium', $document->resource->url);
        self::assertSame('Premium', $document->resource->description);
        self::assertSame('10000', $document->first()->amount);
    }

    #[Test]
    public function invalidHeaderValuesThrow(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentRequired::fromHeaderValue('not base64!');
    }

    #[Test]
    public function documentsWithoutRequirementsThrow(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        PaymentRequired::fromHeaderValue(base64_encode('{"x402Version":2,"accepts":[]}'));
    }
}
