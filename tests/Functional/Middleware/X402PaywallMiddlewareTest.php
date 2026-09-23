<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Functional\Middleware;

use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Webconsulting\X402Paywall\Domain\Model\PaymentRequired;

/**
 * Runs the real TYPO3 frontend middleware stack against a gated page.
 */
final class X402PaywallMiddlewareTest extends FunctionalTestCase
{
    private const string WALLET = '0x1111111111111111111111111111111111111111';

    protected array $testExtensionsToLoad = ['webconsulting/typo3-x402-paywall'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->writeSiteConfiguration();
        $this->setUpFrontendRootPage(1, ['EXT:x402_paywall/tests/Functional/Fixtures/TypoScript/setup.typoscript']);
    }

    #[Test]
    public function gatedPageAnswers402WithAnX402V2PaymentRequiredHeader(): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('https://acme.test/premium'))->withHeader('Accept', 'application/json'),
        );

        self::assertSame(402, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $document = PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'));
        self::assertSame(2, $document->toArray()['x402Version']);
        self::assertSame('https://acme.test/premium', $document->resource->url);
        self::assertSame('Premium article', $document->resource->description);
        self::assertSame('exact', $document->first()->scheme);
        self::assertSame('eip155:84532', $document->first()->network);
        self::assertSame('50000', $document->first()->amount);
        self::assertSame('0x036CbD53842c5426634e7929541eC2318f3dCF7e', $document->first()->asset);
        self::assertSame(self::WALLET, $document->first()->payTo);

        $body = json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame($document->toArray(), $body);
    }

    #[Test]
    public function browsersReceiveTheWalletPaywallPage(): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('https://acme.test/premium'))->withHeader('Accept', 'text/html,application/xhtml+xml'),
        );

        self::assertSame(402, $response->getStatusCode());
        self::assertStringStartsWith('text/html', $response->getHeaderLine('Content-Type'));
        $html = (string)$response->getBody();
        self::assertStringContainsString('data-x402-paywall', $html);
        self::assertStringContainsString('0.05 USDC', $html);
        self::assertStringContainsString('Payment required', $html);
        self::assertStringContainsString('paywall.js', $html);
        self::assertStringNotContainsString('Hello from TYPO3', $html);
    }

    #[Test]
    public function routePatternsGatePagesWithoutTheToggle(): void
    {
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('https://acme.test/premium-by-route'))->withHeader('Accept', 'application/json'),
        );

        self::assertSame(402, $response->getStatusCode());
        $document = PaymentRequired::fromHeaderValue($response->getHeaderLine('PAYMENT-REQUIRED'));
        self::assertSame('10000', $document->first()->amount);
        self::assertSame('By route', $document->resource->description);
    }

    #[Test]
    public function ungatedPagesRenderNormally(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('https://acme.test/free'));

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Hello from TYPO3', (string)$response->getBody());
        self::assertSame('', $response->getHeaderLine('PAYMENT-REQUIRED'));
    }

    private function writeSiteConfiguration(): void
    {
        $configuration = [
            'rootPageId' => 1,
            'base' => 'https://acme.test/',
            'languages' => [[
                'languageId' => 0,
                'title' => 'English',
                'navigationTitle' => 'English',
                'base' => '/',
                'locale' => 'en_US.UTF-8',
                'flag' => 'us',
            ]],
            'errorHandling' => [],
            'routes' => [],
            'x402_paywall' => [
                'enabled' => true,
                'wallet_address' => self::WALLET,
                'network' => 'base-sepolia',
                'facilitator_url' => 'https://x402.org/facilitator',
                'default_price' => '0.01',
                'gated_route_patterns' => ['/premium-by-route'],
            ],
        ];

        $directory = $this->instancePath . '/typo3conf/sites/acme';
        GeneralUtility::mkdir_deep($directory);
        GeneralUtility::writeFile($directory . '/config.yaml', Yaml::dump($configuration, 99, 2), true);
    }
}
