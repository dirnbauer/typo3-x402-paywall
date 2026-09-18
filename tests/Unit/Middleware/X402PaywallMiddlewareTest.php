<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Middleware;

use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\HtmlResponse;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Http\RequestFactory;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Configuration\ConfigurationProvider;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Event\PaymentReceivedEvent;
use Webconsulting\X402Paywall\Event\PaymentRequiredEvent;
use Webconsulting\X402Paywall\Http\PaymentRequiredResponseFactory;
use Webconsulting\X402Paywall\Middleware\X402PaywallMiddleware;
use Webconsulting\X402Paywall\Service\ContentTypeResolver;
use Webconsulting\X402Paywall\Service\PaymentLogger;
use Webconsulting\X402Paywall\Service\PaymentVerifier;
use Webconsulting\X402Paywall\Service\RouteGateResolver;
use Webconsulting\X402Paywall\Tests\Unit\JsonTestTrait;
use Webconsulting\X402Paywall\Utility\Json;

final class X402PaywallMiddlewareTest extends UnitTestCase
{
    use JsonTestTrait;

    private const WALLET = '0x1111111111111111111111111111111111111111';
    private const USDC_SEPOLIA = '0x036CbD53842c5426634e7929541eC2318f3dCF7e';

    /** @var list<ResponseInterface> */
    private array $facilitatorResponses = [];

    /** @var list<array{url: string, json: array<array-key, mixed>}> */
    private array $facilitatorRequests = [];

    /** @var list<array<string, mixed>> */
    private array $loggedRows = [];

    /** @var list<object> */
    private array $events = [];

    private bool $handlerCalled = false;

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS'] = ['SYS' => ['encryptionKey' => str_repeat('b', 64)]];
    }

    #[Test]
    public function ungatedRequestsPassThrough(): void
    {
        $response = $this->middleware()->process($this->request('/free', gated: false), $this->handler());

        self::assertTrue($this->handlerCalled);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame([], $this->events);
    }

    #[Test]
    public function invalidConfigurationPassesThrough(): void
    {
        $response = $this->middleware()->process($this->request('/premium', siteConfig: ['enabled' => true, 'wallet_address' => '']), $this->handler());

        self::assertTrue($this->handlerCalled);
        self::assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function gatedRequestWithoutPaymentReturns402WithPaymentRequiredHeader(): void
    {
        $response = $this->middleware()->process($this->request('/premium'), $this->handler());

        self::assertFalse($this->handlerCalled);
        self::assertSame(402, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $document = PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'));
        self::assertSame('https://example.test/premium', $document->resource->url);
        self::assertSame('Premium article', $document->resource->description);
        self::assertSame('application/json', $document->resource->mimeType);
        self::assertSame('exact', $document->first()->scheme);
        self::assertSame('eip155:84532', $document->first()->network);
        self::assertSame('50000', $document->first()->amount);
        self::assertSame(self::USDC_SEPOLIA, $document->first()->asset);
        self::assertSame(self::WALLET, $document->first()->payTo);
        self::assertSame(['name' => 'USDC', 'version' => '2'], $document->first()->extra);

        self::assertCount(1, $this->events);
        self::assertInstanceOf(PaymentRequiredEvent::class, $this->events[0]);
        self::assertSame('0.05', $this->events[0]->price);
        self::assertSame('eip155:84532', $this->events[0]->network);
    }

    #[Test]
    public function undecodablePaymentHeaderIsRejectedWithInvalidPayload(): void
    {
        $request = $this->request('/premium')->withHeader('PAYMENT-SIGNATURE', 'not-base64!');

        $response = $this->middleware()->process($request, $this->handler());

        self::assertSame(402, $response->getStatusCode());
        self::assertSame('invalid_payload', PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'))->error);
        self::assertSame([], $this->facilitatorRequests);
    }

    #[Test]
    public function payloadForAnotherRequirementIsRejectedBeforeContactingTheFacilitator(): void
    {
        $request = $this->request('/premium')->withHeader('PAYMENT-SIGNATURE', $this->paymentSignature(['amount' => '1']));

        $response = $this->middleware()->process($request, $this->handler());

        self::assertSame(402, $response->getStatusCode());
        self::assertSame('invalid_payment_requirements', PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'))->error);
        self::assertSame([], $this->facilitatorRequests);
    }

    #[Test]
    public function verifiedAndSettledPaymentServesTheContentWithPaymentResponseHeader(): void
    {
        $this->facilitatorResponses = [
            new JsonResponse(['isValid' => true, 'payer' => '0xPayer']),
            new JsonResponse(['success' => true, 'transaction' => '0xtx', 'network' => 'eip155:84532', 'payer' => '0xPayer', 'amount' => '50000']),
        ];
        $request = $this->request('/premium')->withHeader('PAYMENT-SIGNATURE', $this->paymentSignature());

        $response = $this->middleware()->process($request, $this->handler());

        self::assertTrue($this->handlerCalled);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('secret content', (string)$response->getBody());

        $settlementHeader = base64_decode($response->getHeaderLine('PAYMENT-RESPONSE'), true);
        self::assertIsString($settlementHeader);
        $settlement = Json::decodeObject($settlementHeader);
        self::assertSame(['success' => true, 'transaction' => '0xtx', 'network' => 'eip155:84532', 'payer' => '0xPayer', 'amount' => '50000'], $settlement);
        self::assertSame('', $response->getHeaderLine('X-PAYMENT-RESPONSE'));

        self::assertSame('https://facilitator.test/verify', $this->facilitatorRequests[0]['url']);
        self::assertSame('https://facilitator.test/settle', $this->facilitatorRequests[1]['url']);
        self::assertSame(2, self::jsonPath($this->facilitatorRequests[0]['json'], 'x402Version'));
        self::assertSame('50000', self::jsonPath($this->facilitatorRequests[0]['json'], 'paymentRequirements', 'amount'));
        self::assertSame('0xPayer', self::jsonPath($this->facilitatorRequests[0]['json'], 'paymentPayload', 'payload', 'authorization', 'from'));

        self::assertCount(1, $this->loggedRows);
        self::assertSame('settled', $this->loggedRows[0]['status']);
        self::assertSame('0xtx', $this->loggedRows[0]['tx_hash']);
        self::assertSame('0xPayer', $this->loggedRows[0]['payer_address']);
        self::assertSame('0.05', $this->loggedRows[0]['amount']);
        self::assertSame(2, $this->loggedRows[0]['page_uid']);

        self::assertCount(1, $this->events);
        self::assertInstanceOf(PaymentReceivedEvent::class, $this->events[0]);
        self::assertSame('0xtx', $this->events[0]->txHash);
        self::assertSame('0xPayer', $this->events[0]->payer);
    }

    #[Test]
    public function rejectedVerificationReturns402WithTheInvalidReason(): void
    {
        $this->facilitatorResponses = [new JsonResponse(['isValid' => false, 'invalidReason' => 'insufficient_funds'])];
        $request = $this->request('/premium')->withHeader('PAYMENT-SIGNATURE', $this->paymentSignature());

        $response = $this->middleware()->process($request, $this->handler());

        self::assertFalse($this->handlerCalled);
        self::assertSame(402, $response->getStatusCode());
        self::assertSame('insufficient_funds', PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'))->error);
        self::assertSame([], $this->loggedRows);
    }

    #[Test]
    public function silentFacilitatorsRejectThePayment(): void
    {
        $request = $this->request('/premium')->withHeader('PAYMENT-SIGNATURE', $this->paymentSignature());

        $response = $this->middleware()->process($request, $this->handler());

        self::assertFalse($this->handlerCalled);
        self::assertSame(402, $response->getStatusCode());
        self::assertSame('verification_failed', PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'))->error);
    }

    #[Test]
    public function failedSettlementReturns402AndLogsTheFailure(): void
    {
        $this->facilitatorResponses = [
            new JsonResponse(['isValid' => true, 'payer' => '0xPayer']),
            new JsonResponse(['success' => false, 'errorReason' => 'invalid_transaction_state', 'transaction' => '', 'network' => 'eip155:84532']),
        ];
        $request = $this->request('/premium')->withHeader('PAYMENT-SIGNATURE', $this->paymentSignature());

        $response = $this->middleware()->process($request, $this->handler());

        self::assertSame(402, $response->getStatusCode());
        self::assertSame('invalid_transaction_state', PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'))->error);
        self::assertSame('failed', $this->loggedRows[0]['status']);
        self::assertSame([], $this->events);
    }

    #[Test]
    public function failingResourcesAreNotSettled(): void
    {
        $this->facilitatorResponses = [new JsonResponse(['isValid' => true, 'payer' => '0xPayer'])];
        $request = $this->request('/premium')->withHeader('PAYMENT-SIGNATURE', $this->paymentSignature());

        $response = $this->middleware()->process($request, $this->handler(new Response(null, 500)));

        self::assertSame(500, $response->getStatusCode());
        self::assertCount(1, $this->facilitatorRequests);
        self::assertSame([], $this->loggedRows);
    }

    #[Test]
    public function legacyV1PayloadsAreRejectedUnlessEnabled(): void
    {
        $v1 = base64_encode(json_encode([
            'x402Version' => 1,
            'scheme' => 'exact',
            'network' => 'base-sepolia',
            'payload' => ['signature' => '0xsig', 'authorization' => ['from' => '0xPayer']],
        ], JSON_THROW_ON_ERROR));

        $response = $this->middleware()->process($this->request('/premium')->withHeader('PAYMENT-SIGNATURE', $v1), $this->handler());
        self::assertSame(402, $response->getStatusCode());
        self::assertSame('invalid_x402_version', PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'))->error);

        $this->facilitatorResponses = [
            new JsonResponse(['isValid' => true, 'payer' => '0xPayer']),
            new JsonResponse(['success' => true, 'transaction' => '0xtx', 'network' => 'base-sepolia']),
        ];
        $request = $this->request('/premium', siteConfig: ['legacy_v1' => true])->withHeader('X-PAYMENT', $v1)->withHeader('Accept', 'application/json');

        $response = $this->middleware()->process($request, $this->handler());

        self::assertSame(200, $response->getStatusCode());
        self::assertNotSame('', $response->getHeaderLine('PAYMENT-RESPONSE'));
        self::assertSame($response->getHeaderLine('PAYMENT-RESPONSE'), $response->getHeaderLine('X-PAYMENT-RESPONSE'));
        self::assertSame(1, self::jsonPath($this->facilitatorRequests[0]['json'], 'x402Version'));
        self::assertSame('base-sepolia', self::jsonPath($this->facilitatorRequests[0]['json'], 'paymentRequirements', 'network'));
        self::assertSame('50000', self::jsonPath($this->facilitatorRequests[0]['json'], 'paymentRequirements', 'maxAmountRequired'));
        self::assertSame('exact', self::jsonPath($this->facilitatorRequests[0]['json'], 'paymentPayload', 'scheme'));
        self::assertSame('0xPayer', self::jsonPath(Json::decodeObject((string)base64_decode($response->getHeaderLine('PAYMENT-RESPONSE'), true)), 'payer'));
    }

    #[Test]
    public function legacyModeAnswersUnpaidRequestsWithTheV1Body(): void
    {
        $request = $this->request('/premium', siteConfig: ['legacy_v1' => true])->withHeader('Accept', 'application/json');

        $response = $this->middleware()->process($request, $this->handler());
        $body = Json::decodeObject((string)$response->getBody());

        self::assertSame(1, $body['x402Version']);
        self::assertSame('50000', self::jsonPath($body, 'accepts', 0, 'maxAmountRequired'));
        self::assertSame(2, PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'))->toArray()['x402Version']);
    }

    /**
     * @param array<string, mixed> $acceptedOverrides
     */
    private function paymentSignature(array $acceptedOverrides = []): string
    {
        $accepted = array_merge([
            'scheme' => 'exact',
            'network' => 'eip155:84532',
            'amount' => '50000',
            'asset' => self::USDC_SEPOLIA,
            'payTo' => self::WALLET,
            'maxTimeoutSeconds' => 300,
            'extra' => ['name' => 'USDC', 'version' => '2'],
        ], $acceptedOverrides);

        return base64_encode(json_encode([
            'x402Version' => 2,
            'resource' => ['url' => 'https://example.test/premium'],
            'accepted' => $accepted,
            'payload' => [
                'signature' => '0xsig',
                'authorization' => ['from' => '0xPayer', 'to' => self::WALLET, 'value' => '50000', 'validAfter' => '0', 'validBefore' => '9999999999', 'nonce' => '0x00'],
            ],
        ], JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed> $siteConfig
     */
    private function request(string $path, bool $gated = true, array $siteConfig = []): ServerRequestInterface
    {
        $site = new Site('main', 1, [
            'base' => 'https://example.test/',
            'x402_paywall' => array_merge([
                'enabled' => true,
                'wallet_address' => self::WALLET,
                'network' => 'base-sepolia',
                'facilitator_url' => 'https://facilitator.test',
                'default_price' => '0.01',
            ], $siteConfig),
        ]);
        $pageInformation = new PageInformation();
        $pageInformation->setId(2);
        $pageInformation->setPageRecord([
            'uid' => 2,
            'title' => 'Premium article',
            'tx_x402_paywall_enabled' => $gated ? 1 : 0,
            'tx_x402_paywall_price' => '0.05',
            'tx_x402_paywall_description' => '',
        ]);

        return (new ServerRequest('https://example.test' . $path, 'GET', null, ['Accept' => 'application/json']))
            ->withAttribute('site', $site)
            ->withAttribute('routing', new PageArguments(2, '0', []))
            ->withAttribute('frontend.page.information', $pageInformation);
    }

    private function handler(?ResponseInterface $response = null): RequestHandlerInterface
    {
        $test = $this;

        return new class ($response ?? new HtmlResponse('secret content'), $test) implements RequestHandlerInterface {
            public function __construct(private readonly ResponseInterface $response, private readonly X402PaywallMiddlewareTest $test) {}

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                $this->test->markHandlerCalled();

                return $this->response;
            }
        };
    }

    public function markHandlerCalled(): void
    {
        $this->handlerCalled = true;
    }

    private function middleware(): X402PaywallMiddleware
    {
        $requestFactory = self::createStub(RequestFactory::class);
        $requestFactory->method('request')->willReturnCallback(function (string $url, string $method, array $options): ResponseInterface {
            $json = $options['json'] ?? null;
            $this->facilitatorRequests[] = ['url' => $url, 'json' => is_array($json) ? $json : []];

            return array_shift($this->facilitatorResponses) ?? new Response(null, 500);
        });

        $connection = self::createStub(Connection::class);
        $connection->method('insert')->willReturnCallback(function (string $table, array $data): int {
            $row = [];
            foreach ($data as $key => $value) {
                $row[(string)$key] = $value;
            }
            $this->loggedRows[] = $row;

            return 1;
        });
        $connectionPool = self::createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->willReturn($connection);

        $eventDispatcher = self::createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(function (object $event): object {
            $this->events[] = $event;

            return $event;
        });

        return new X402PaywallMiddleware(
            new ConfigurationProvider(),
            new RouteGateResolver(),
            new PaymentVerifier($requestFactory, new NullLogger()),
            new PaymentLogger($connectionPool, new HashService()),
            new ContentTypeResolver(),
            new PaymentRequiredResponseFactory(new ResponseFactory(), new StreamFactory(), self::createStub(ViewFactoryInterface::class)),
            $eventDispatcher,
            new NullLogger(),
        );
    }
}
