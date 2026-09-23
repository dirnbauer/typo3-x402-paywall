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

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Domain\Model\PaymentPayload;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Service\PaymentVerifier;
use Webconsulting\X402Paywall\Utility\Json;

final class PaymentVerifierTest extends UnitTestCase
{
    private PaywallConfiguration $config;
    private PaymentRequirement $requirement;
    private PaymentPayload $payload;

    /** @var list<array{url: string, method: string, raw: string, json: array<array-key, mixed>, headers: array<array-key, mixed>}> */
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
        self::assertSame('application/json', $this->requests[0]['headers']['Content-Type'] ?? null);
        self::assertArrayNotHasKey('Authorization', $this->requests[0]['headers']);
    }

    #[Test]
    public function emptyObjectsInThePayloadStayObjects(): void
    {
        $payload = PaymentPayload::fromHeaderValue(base64_encode('{"x402Version":2,"accepted":{"scheme":"exact","network":"eip155:84532"},"payload":{"signature":"0xsig","authorization":{"from":"0xPayer"}},"extensions":{}}'));
        $verifier = $this->verifier([new JsonResponse(['isValid' => true])]);

        $verifier->verify($payload, $this->requirement->toArray(), $this->config);

        self::assertStringContainsString('"extensions":{}', $this->requests[0]['raw']);
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
        $verifier = $this->verifier([
            new JsonResponse(['errorType' => 'internal_server_error', 'errorMessage' => 'Internal server error'], 500),
            new HtmlResponse('<h1>502</h1>', 502),
        ]);

        $apiError = $verifier->verify($this->payload, $this->requirement->toArray(), $this->config);
        $gatewayError = $verifier->verify($this->payload, $this->requirement->toArray(), $this->config);

        self::assertSame('unexpected_verify_error', $apiError->invalidReason);
        self::assertSame('Internal server error', $apiError->invalidMessage);
        self::assertSame('unexpected_verify_error', $gatewayError->invalidReason);
        self::assertSame('HTTP 502 from the facilitator', $gatewayError->invalidMessage);
    }

    #[Test]
    public function unreachableFacilitatorsFailVerificationAndSettlement(): void
    {
        $requestFactory = self::createStub(RequestFactory::class);
        $requestFactory->method('request')->willThrowException(new ConnectException('Connection refused', new Request('https://facilitator.test/settle')));
        $verifier = new PaymentVerifier($requestFactory, new NullLogger());

        $verification = $verifier->verify($this->payload, $this->requirement->toArray(), $this->config);
        $settlement = $verifier->settle($this->payload, $this->requirement->toArray(), $this->config);

        self::assertFalse($verification->isValid);
        self::assertSame('unexpected_verify_error', $verification->invalidReason);
        self::assertFalse($settlement->success);
        self::assertFalse($settlement->isPending());
        self::assertSame('unexpected_settle_error', $settlement->errorReason);
        self::assertSame('eip155:84532', $settlement->network);
        self::assertSame('0xPayer', $settlement->payer);
    }

    #[Test]
    public function settleRequestsThatGetNoAnswerHaveAnUnknownOutcome(): void
    {
        $request = new Request('https://facilitator.test/settle');
        foreach ([new RequestException('Connection reset by peer', $request), new ConnectException('Operation timed out', $request)] as $exception) {
            $requestFactory = self::createStub(RequestFactory::class);
            $requestFactory->method('request')->willThrowException($exception);
            // A timeout of 0 seconds makes every connection error look like a response timeout (Guzzle 7).
            $verifier = new PaymentVerifier($requestFactory, new NullLogger(), settleTimeout: 0);

            $settlement = $verifier->settle($this->payload, $this->requirement->toArray(), $this->config);

            self::assertTrue($settlement->outcomeUnknown, $exception->getMessage());
            self::assertTrue($settlement->isPending());
            self::assertSame('unexpected_settle_error', $settlement->toArray()['errorReason'] ?? '');
        }
    }

    #[Test]
    public function pendingSettlementsAreRetriedOnceKeepingTheTransactionHash(): void
    {
        $pending = new JsonResponse(['success' => false, 'errorReason' => 'settlement_pending', 'transaction' => '0xabc', 'network' => 'eip155:84532'], 500);
        $requests = 0;
        $requestFactory = self::createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturnCallback(static function () use ($pending, &$requests): ResponseInterface {
            if (++$requests === 1) {
                return $pending;
            }
            throw new RequestException('Connection reset by peer', new Request('https://facilitator.test/settle'));
        });
        $verifier = new PaymentVerifier($requestFactory, new NullLogger());

        $settlement = $verifier->settle($this->payload, $this->requirement->toArray(), $this->config);

        self::assertSame(2, $requests);
        self::assertTrue($settlement->isSettlementPending());
        self::assertSame('0xabc', $settlement->transaction);
    }

    #[Test]
    public function cdpFacilitatorsGetASignedBearerToken(): void
    {
        $keyPair = sodium_crypto_sign_keypair();
        $config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xReceiver',
            'network' => 'base',
            'facilitator_url' => 'https://api.cdp.coinbase.com/platform/v2/x402',
            'facilitator_auth' => 'cdp',
            'facilitator_api_key_id' => 'key-id',
            'facilitator_api_key_secret' => base64_encode(sodium_crypto_sign_secretkey($keyPair)),
        ]);
        $verifier = $this->verifier([new JsonResponse(['isValid' => true])]);

        $verifier->verify($this->payload, $this->requirement->toArray(), $config);

        $authorization = $this->requests[0]['headers']['Authorization'] ?? '';
        self::assertIsString($authorization);
        self::assertStringStartsWith('Bearer ', $authorization);
        [$header, $claims, $signature] = explode('.', substr($authorization, 7));
        self::assertSame('EdDSA', Json::decodeObject(self::base64UrlDecode($header))['alg']);
        self::assertSame(['POST api.cdp.coinbase.com/platform/v2/x402/verify'], Json::decodeObject(self::base64UrlDecode($claims))['uris']);
        self::assertTrue(sodium_crypto_sign_verify_detached(self::base64UrlDecode($signature), $header . '.' . $claims, sodium_crypto_sign_publickey($keyPair)));
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
        self::assertSame('unexpected_settle_error', $this->verifier([new Response()])->settle($this->payload, [], $this->config)->errorReason);
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
                $raw = is_string($options['body'] ?? null) ? $options['body'] : '';
                $headers = is_array($options['headers'] ?? null) ? $options['headers'] : [];
                $this->requests[] = ['url' => $url, 'method' => $method, 'raw' => $raw, 'json' => $raw !== '' ? Json::decodeObject($raw) : [], 'headers' => $headers];

                return array_shift($responses) ?? new Response(null, 500);
            });

        return new PaymentVerifier($requestFactory, new NullLogger());
    }

    /**
     * @return non-empty-string
     */
    private static function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        self::assertIsString($decoded);
        self::assertNotSame('', $decoded);

        return $decoded;
    }
}
