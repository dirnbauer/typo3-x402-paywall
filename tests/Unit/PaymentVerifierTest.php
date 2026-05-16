<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Tests\Unit;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Service\PaymentVerifier;

final class PaymentVerifierTest extends TestCase
{
    private PaywallConfiguration $config;

    protected function setUp(): void
    {
        $this->config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xTestWallet',
            'network' => 'base-sepolia',
            'facilitator_url' => 'https://x402.org/facilitator',
            'default_price' => '0.01',
        ]);
    }

    public function testVerifyReturnsTrueWhenFacilitatorAccepts(): void
    {
        $verifier = $this->makeVerifier([
            new Response(200, [], json_encode(['valid' => true, 'details' => []])),
        ]);

        $result = $verifier->verify('mock-signature-b64', 'mock-requirement-b64', $this->config);

        self::assertTrue($result['valid']);
    }

    public function testVerifyReturnsFalseWhenFacilitatorRejects(): void
    {
        $verifier = $this->makeVerifier([
            new Response(200, [], json_encode(['valid' => false, 'error' => 'Insufficient funds'])),
        ]);

        $result = $verifier->verify('mock-signature-b64', 'mock-requirement-b64', $this->config);

        self::assertFalse($result['valid']);
        self::assertSame('Insufficient funds', $result['error']);
    }

    public function testVerifyReturnsFalseOnFacilitatorError(): void
    {
        $verifier = $this->makeVerifier([
            new Response(500, [], json_encode(['message' => 'Internal server error'])),
        ]);

        $result = $verifier->verify('mock-signature-b64', 'mock-requirement-b64', $this->config);

        self::assertFalse($result['valid']);
    }

    public function testSettleReturnsTxHashOnSuccess(): void
    {
        $txHash = '0xdeadbeef1234567890abcdef';
        $verifier = $this->makeVerifier([
            new Response(200, [], json_encode(['settled' => true, 'txHash' => $txHash])),
        ]);

        $result = $verifier->settle('mock-signature-b64', 'mock-requirement-b64', $this->config);

        self::assertTrue($result['settled']);
        self::assertSame($txHash, $result['txHash']);
    }

    public function testSettleReturnsFalseOnFailure(): void
    {
        $verifier = $this->makeVerifier([
            new Response(200, [], json_encode(['settled' => false, 'error' => 'Already settled'])),
        ]);

        $result = $verifier->settle('mock-signature-b64', 'mock-requirement-b64', $this->config);

        self::assertFalse($result['settled']);
        self::assertSame('Already settled', $result['error']);
    }

    public function testTestConnectionReturnsTrueOnHealthyFacilitator(): void
    {
        $verifier = $this->makeVerifier([
            new Response(200, [], '{"status":"ok"}'),
        ]);

        self::assertTrue($verifier->testConnection($this->config));
    }

    public function testTestConnectionReturnsFalseOnServerError(): void
    {
        $verifier = $this->makeVerifier([
            new Response(500, [], ''),
        ]);

        self::assertFalse($verifier->testConnection($this->config));
    }

    private function makeVerifier(array $responses): PaymentVerifier
    {
        $mock = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        return new PaymentVerifier($client, new NullLogger());
    }
}
