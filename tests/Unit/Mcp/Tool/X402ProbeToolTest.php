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
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Domain\Model\ResourceInfo;
use Webconsulting\X402Paywall\Mcp\Tool\X402ProbeTool;
use Webconsulting\X402Paywall\Tests\Unit\JsonTestTrait;

final class X402ProbeToolTest extends UnitTestCase
{
    use JsonTestTrait;

    private const URL = 'https://93.184.216.34/premium';

    #[Test]
    public function toolIsNamedX402Probe(): void
    {
        self::assertSame('x402_probe', $this->tool(new Response())->getName());
    }

    #[Test]
    public function decodesTheV2PaymentRequiredHeaderOf402Responses(): void
    {
        $document = new PaymentRequired(
            new ResourceInfo(self::URL, 'Premium'),
            [new PaymentRequirement('exact', 'eip155:84532', '10000', '0xToken', '0xReceiver')],
        );
        $response = (new JsonResponse($document->toArray(), 402))->withHeader('PAYMENT-REQUIRED', $document->toHeaderValue());

        $result = self::decodeJsonObject($this->tool($response)->execute(['url' => self::URL]));

        self::assertSame(402, $result['status']);
        self::assertTrue($result['paywall']);
        self::assertSame(2, $result['x402Version']);
        self::assertFalse($result['legacy']);
        self::assertSame($document->toArray(), $result['paymentRequired']);
        self::assertIsString($result['summary']);
        self::assertStringContainsString('10000 atomic units (0.01 if 6 decimals)', $result['summary']);
    }

    #[Test]
    public function recognisesLegacyV1BodiesWithoutHeader(): void
    {
        $body = [
            'x402Version' => 1,
            'error' => 'Payment required',
            'accepts' => [['scheme' => 'exact', 'network' => 'base-sepolia', 'maxAmountRequired' => '10000', 'resource' => self::URL, 'payTo' => '0xReceiver', 'asset' => '0xToken']],
        ];

        $result = self::decodeJsonObject($this->tool(new JsonResponse($body, 402))->execute(['url' => self::URL]));

        self::assertTrue($result['paywall']);
        self::assertTrue($result['legacy']);
        self::assertSame(1, $result['x402Version']);
        self::assertSame($body, $result['paymentRequired']);
    }

    #[Test]
    public function reportsFreeResourcesAndRejectsPrivateTargets(): void
    {
        $free = self::decodeJsonObject($this->tool(new Response())->execute(['url' => self::URL]));
        self::assertFalse($free['paywall']);
        self::assertSame(200, $free['status']);

        $private = self::decodeJsonObject($this->tool(new Response())->execute(['url' => 'http://127.0.0.1/']));
        self::assertSame('URL is not allowed for server-side probes', $private['error']);
    }

    private function tool(ResponseInterface $response): X402ProbeTool
    {
        $requestFactory = self::createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturn($response);

        return new X402ProbeTool($requestFactory);
    }
}
