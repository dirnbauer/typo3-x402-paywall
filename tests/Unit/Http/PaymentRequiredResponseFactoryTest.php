<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Http;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ResponseFactory;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\CMS\Core\View\ViewFactoryInterface;
use TYPO3\CMS\Core\View\ViewInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequirement;
use Webconsulting\X402Paywall\Domain\Model\ResourceInfo;
use Webconsulting\X402Paywall\Http\PaymentRequiredResponseFactory;
use Webconsulting\X402Paywall\Tests\Unit\JsonTestTrait;

final class PaymentRequiredResponseFactoryTest extends UnitTestCase
{
    use JsonTestTrait;

    private PaywallConfiguration $config;
    private PaymentRequired $paymentRequired;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = PaywallConfiguration::fromArray(['enabled' => true, 'wallet_address' => '0xReceiver', 'network' => 'base-sepolia']);
        $this->paymentRequired = new PaymentRequired(
            new ResourceInfo('https://example.test/premium', 'Premium'),
            [PaymentRequirement::fromConfig($this->config, '0.01')],
        );
    }

    #[Test]
    public function jsonClientsReceiveTheV2DocumentInHeaderAndBody(): void
    {
        $request = (new ServerRequest('https://example.test/premium'))->withHeader('Accept', 'application/json');

        $response = $this->factory()->create($request, $this->paymentRequired, $this->config);

        self::assertSame(402, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));
        self::assertSame('no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame($this->paymentRequired->toArray(), PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'))->toArray());
        self::assertSame($this->paymentRequired->toArray(), self::decodeJsonObject((string)$response->getBody()));
    }

    #[Test]
    public function errorsAreAddedToTheDocument(): void
    {
        $request = (new ServerRequest('https://example.test/premium'))->withHeader('Accept', '*/*');

        $response = $this->factory()->create($request, $this->paymentRequired, $this->config, 'insufficient_funds');

        self::assertSame('insufficient_funds', PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'))->error);
    }

    #[Test]
    public function legacyModeSendsTheV1BodyButKeepsTheV2Header(): void
    {
        $config = PaywallConfiguration::fromArray(['enabled' => true, 'wallet_address' => '0xReceiver', 'network' => 'base-sepolia', 'legacy_v1' => true]);
        $request = (new ServerRequest('https://example.test/premium'))->withHeader('Accept', 'application/json');

        $response = $this->factory()->create($request, $this->paymentRequired, $config);
        $body = self::decodeJsonObject((string)$response->getBody());

        self::assertSame(1, $body['x402Version']);
        self::assertSame('base-sepolia', self::jsonPath($body, 'accepts', 0, 'network'));
        self::assertSame('10000', self::jsonPath($body, 'accepts', 0, 'maxAmountRequired'));
        self::assertSame(2, PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'))->toArray()['x402Version']);
    }

    #[Test]
    public function browsersReceiveTheRenderedPaywallPage(): void
    {
        $request = (new ServerRequest('https://example.test/premium'))->withHeader('Accept', 'text/html,application/xhtml+xml');
        $assigned = [];
        $view = $this->createMock(ViewInterface::class);
        $view->expects($this->once())->method('assignMultiple')->willReturnCallback(static function (array $values) use (&$assigned, $view): ViewInterface {
            $assigned = $values;

            return $view;
        });
        $view->expects($this->once())->method('render')->with('PaymentRequired')->willReturn('<html>paywall</html>');
        $viewFactory = self::createStub(ViewFactoryInterface::class);
        $viewFactory->method('create')->willReturn($view);

        $factory = new PaymentRequiredResponseFactory(new ResponseFactory(), new StreamFactory(), $viewFactory);
        self::assertTrue($factory->isBrowserRequest($request));

        $response = $factory->create($request, $this->paymentRequired, $this->config);

        self::assertSame(402, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        self::assertSame('<html>paywall</html>', (string)$response->getBody());
        self::assertNotSame('', $response->getHeaderLine('PAYMENT-REQUIRED'));
        self::assertSame('0.01', $assigned['price']);
        self::assertSame('USDC', $assigned['currency']);
        self::assertSame('eip155:84532', $assigned['network']);
        self::assertSame(84532, $assigned['chainId']);
        self::assertSame('Premium', $assigned['description']);
    }

    private function factory(): PaymentRequiredResponseFactory
    {
        return new PaymentRequiredResponseFactory(new ResponseFactory(), new StreamFactory(), self::createStub(ViewFactoryInterface::class));
    }
}
