<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Legacy;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Domain\Model\PaymentPayload;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Domain\Model\ResourceInfo;
use Webconsulting\X402Paywall\Legacy\X402V1;

final class X402V1Test extends UnitTestCase
{
    #[Test]
    public function requirementToArrayProducesX402V1Fields(): void
    {
        $config = PaywallConfiguration::fromArray(['enabled' => true, 'wallet_address' => '0xReceiver', 'network' => 'base-sepolia']);
        $requirement = PaymentRequirement::fromConfig($config, '0.01');

        $legacy = X402V1::requirementToArray($requirement, new ResourceInfo('https://example.test/premium', 'Premium', 'text/html'), 'base-sepolia');

        self::assertSame('base-sepolia', $legacy['network']);
        self::assertSame('10000', $legacy['maxAmountRequired']);
        self::assertSame('https://example.test/premium', $legacy['resource']);
        self::assertSame('Premium', $legacy['description']);
        self::assertSame('text/html', $legacy['mimeType']);
        self::assertSame('0x036CbD53842c5426634e7929541eC2318f3dCF7e', $legacy['asset']);
        self::assertSame(['name' => 'USDC', 'version' => '2'], $legacy['extra']);
        self::assertArrayNotHasKey('amount', $legacy);
    }

    #[Test]
    public function paymentRequiredToArrayProducesTheV1Body(): void
    {
        $document = new PaymentRequired(
            new ResourceInfo('https://example.test/premium', 'Premium'),
            [new PaymentRequirement('exact', 'eip155:84532', '10000', '0xToken', '0xReceiver')],
        );

        $legacy = X402V1::paymentRequiredToArray($document, 'base-sepolia');

        self::assertSame(1, $legacy['x402Version']);
        self::assertSame('Payment required', $legacy['error']);
        self::assertSame('base-sepolia', $legacy['accepts'][0]['network']);
        self::assertSame('https://example.test/premium', $legacy['accepts'][0]['resource']);
        self::assertNull($legacy['accepts'][0]['extra']);
        self::assertSame('insufficient_funds', X402V1::paymentRequiredToArray($document->withError('insufficient_funds'), 'base-sepolia')['error']);
    }

    #[Test]
    public function paymentRequiredFromArrayParsesV1Bodies(): void
    {
        $document = X402V1::paymentRequiredFromArray([
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
        self::assertSame('Payment required', $document->error);
        self::assertSame('10000', $document->first()->amount);
        self::assertSame('base-sepolia', $document->first()->network);
    }

    #[Test]
    public function paymentRequiredFromArrayRejectsBodiesWithoutRequirements(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        X402V1::paymentRequiredFromArray(['x402Version' => 1, 'accepts' => 'none']);
    }

    #[Test]
    public function v1PayloadsCarrySchemeAndNetworkAtTheTopLevel(): void
    {
        $payload = PaymentPayload::fromArray([
            'x402Version' => 1,
            'scheme' => 'exact',
            'network' => 'base-sepolia',
            'payload' => ['signature' => '0xsig', 'authorization' => ['from' => '0xPayer']],
        ]);
        $requirement = new PaymentRequirement('exact', 'eip155:84532', '10000', '0xToken', '0xReceiver');

        self::assertSame(['scheme' => 'exact', 'network' => 'base-sepolia'], X402V1::acceptedFromPayload(['scheme' => 'exact', 'network' => 'base-sepolia', 'x402Version' => 1]));
        self::assertTrue(X402V1::payloadMatches($payload, $requirement, 'base-sepolia'));
        self::assertFalse(X402V1::payloadMatches($payload, $requirement, 'base'));
        self::assertFalse(X402V1::payloadMatches($payload, new PaymentRequirement('upto', 'eip155:84532', '1', '0xT', '0xR'), 'base-sepolia'));
    }
}
