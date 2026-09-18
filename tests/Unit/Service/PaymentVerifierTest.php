<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Domain\Model\PaymentPayload;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Service\PaymentVerifier;

final class PaymentVerifierTest extends UnitTestCase
{
    private PaywallConfiguration $config;
    private PaymentRequirement $requirement;
    private PaymentPayload $payload;

    /** @var list<array{url: string, method: string, json: array<array-key, mixed>}> */
    private array $requests = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xReceiver',
            'network' => 'base-sepolia',
            'facilitator_url' => 'https://facilitator.test/',
        ]);
        $this->requirement = PaymentRequirement::fromConfig($this->config, '0.01');
        $this->payload = PaymentPayload::fromArray([
            'x402Version' => 2,
            'accepted' => $this->requirement->toArray(),
            'payload' => ['signature' => '0xsig', 'authorization' => ['from' => '0xPayer']],
        ]);
    }

    #[Test]
    public function verifyPostsTheV2RequestShapeAndReturnsThePayer(): void
    {
        $verifier = $this->verifier([new JsonResponse(['isValid' => true, 'payer' => '0xPayer'])]);

        $result = $verifier->verify($this->payload, $this->requirement->toArray(), $this->config);

        self::assertTrue($result->isValid);
        self::assertSame('0xPayer', $result->payer);
        self::assertSame('', $result->invalidReason);
        self::assertSame('https://facilitator.test/verify', $this->requests[0]['url']);
        self::assertSame('POST', $this->requests[0]['method']);
        self::assertSame([
            'x402Version' => 2,
            'paymentPayload' => $this->payload->toArray(),
            'paymentRequirements' => $this->requirement->toArray(),
        ], $this->requests[0]['json']);
    }

    #[Test]
    public function verifyReturnsTheInvalidReasonWhenTheFacilitatorRejects(): void
    {
        $verifier = $this->verifier([new JsonResponse(['isValid' => false, 'invalidReason' => 'insufficient_funds', 'payer' => '0xPayer'])]);

        $result = $verifier->verify($this->payload, $this->requirement->toArray(), $this->config);

        self::assertFalse($result->isValid);
        self::assertSame('insufficient_funds', $result->invalidReason);
    }

    #[Test]
    public function verifyTreatsServerErrorsAndNonJsonBodiesAsInvalid(): void
    {
        $verifier = $this->verifier([new JsonResponse(['message' => 'Internal server error'], 500), new HtmlResponse('<h1>502</h1>', 502)]);

        self::assertSame('Internal server error', $verifier->verify($this->payload, $this->requirement->toArray(), $this->config)->invalidReason);
        self::assertSame('invalid_facilitator_response', $verifier->verify($this->payload, $this->requirement->toArray(), $this->config)->invalidReason);
    }

    #[Test]
    public function unreachableFacilitatorsFailVerificationAndSettlement(): void
    {
        $requestFactory = self::createStub(RequestFactory::class);
        $requestFactory->method('request')->willThrowException(new \RuntimeException('Connection refused'));
        $verifier = new PaymentVerifier($requestFactory, new NullLogger());

        $verification = $verifier->verify($this->payload, $this->requirement->toArray(), $this->config);
        $settlement = $verifier->settle($this->payload, $this->requirement->toArray(), $this->config);

        self::assertFalse($verification->isValid);
        self::assertSame('facilitator_unreachable', $verification->invalidReason);
        self::assertFalse($settlement->success);
        self::assertSame('facilitator_unreachable', $settlement->errorReason);
        self::assertSame('eip155:84532', $settlement->network);
        self::assertSame('0xPayer', $settlement->payer);
    }

    #[Test]
    public function settleReturnsTheSettlementResponseFields(): void
    {
        $verifier = $this->verifier([new JsonResponse([
            'success' => true,
            'transaction' => '0xdeadbeef',
            'network' => 'eip155:84532',
            'payer' => '0xOther',
            'amount' => '10000',
        ])]);

        $result = $verifier->settle($this->payload, $this->requirement->toArray(), $this->config);

        self::assertTrue($result->success);
        self::assertSame('0xdeadbeef', $result->transaction);
        self::assertSame('eip155:84532', $result->network);
        self::assertSame('0xOther', $result->payer);
        self::assertSame('10000', $result->amount);
        self::assertSame('', $result->errorReason);
        self::assertSame('https://facilitator.test/settle', $this->requests[0]['url']);
    }

    #[Test]
    public function settleFallsBackToTheConfiguredNetworkAndThePayloadPayer(): void
    {
        $verifier = $this->verifier([new JsonResponse(['success' => true, 'transaction' => '0x1'])]);

        $result = $verifier->settle($this->payload, $this->requirement->toArray(), $this->config);

        self::assertSame('eip155:84532', $result->network);
        self::assertSame('0xPayer', $result->payer);
    }

    #[Test]
    public function settleReturnsTheErrorReasonOnFailure(): void
    {
        $verifier = $this->verifier([new JsonResponse(['success' => false, 'errorReason' => 'invalid_transaction_state', 'transaction' => '', 'network' => 'eip155:84532'])]);

        $result = $verifier->settle($this->payload, $this->requirement->toArray(), $this->config);

        self::assertFalse($result->success);
        self::assertSame('invalid_transaction_state', $result->errorReason);
        self::assertSame('', $result->transaction);
        self::assertSame('settlement_failed', $this->verifier([new Response()])->settle($this->payload, [], $this->config)->errorReason);
    }

    /**
     * @param list<ResponseInterface> $responses
     */
    private function verifier(array $responses): PaymentVerifier
    {
        $requestFactory = self::createStub(RequestFactory::class);
        $requestFactory
            ->method('request')
            ->willReturnCallback(function (string $url, string $method, array $options) use (&$responses): ResponseInterface {
                $json = $options['json'] ?? null;
                $this->requests[] = ['url' => $url, 'method' => $method, 'json' => is_array($json) ? $json : []];

                return array_shift($responses) ?? new Response(null, 500);
            });

        return new PaymentVerifier($requestFactory, new NullLogger());
    }
}
