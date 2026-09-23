<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Functional\Controller;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Yaml\Yaml;
use TYPO3\CMS\Backend\Module\ModuleProvider;
use TYPO3\CMS\Backend\Routing\Route;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Http\Stream;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;
use Webconsulting\X402Paywall\Controller\PaywallDashboardController;
use Webconsulting\X402Paywall\Controller\PaywallSimulatorController;
use Webconsulting\X402Paywall\Utility\Json;

/**
 * Both submodules render inside the Core module layout; the dashboard reports configuration
 * mistakes, the simulator offers real targets of the site and refuses foreign private hosts.
 */
final class BackendModuleTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = ['webconsulting/typo3-x402-paywall'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages_shop.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tx_x402_payment_log.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/be_users.csv');
        $this->writeSiteConfiguration('acme', 1, 'https://acme.test/', ['network' => 'base-sepolia']);
        $this->writeSiteConfiguration('shop', 10, 'https://shop.test/', ['network' => 'base']);
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($GLOBALS['BE_USER']);
    }

    #[Test]
    public function theDashboardIsANativeModulePageThatReportsConfigurationMistakes(): void
    {
        $body = (string)$this->get(PaywallDashboardController::class)
            ->mainAction($this->moduleRequest(PaywallDashboardController::ROUTE))
            ->getBody();

        self::assertStringContainsString('module-docheader', $body);
        self::assertStringContainsString('<h1>x402 Paywall dashboard</h1>', $body);
        self::assertStringContainsString('<code>acme</code>', $body);
        self::assertStringContainsString('<code>shop</code>', $body);
        self::assertStringContainsString('The x402.org facilitator settles on testnets only.', $body, 'Mainnet payments through the testnet facilitator are flagged');
        self::assertStringContainsString('1.6 USDC', $body);
        self::assertStringContainsString('badge badge-danger">Failed', $body);
        self::assertStringContainsString('0xPayer3', $body);
        self::assertStringNotContainsString('bg-success', $body, 'Badges use the Core styles, not Bootstrap utilities');
    }

    #[Test]
    public function theSimulatorTargetsTheFirstPaywalledPageOfTheSite(): void
    {
        $body = (string)$this->get(PaywallSimulatorController::class)
            ->mainAction($this->moduleRequest(PaywallSimulatorController::ROUTE)->withQueryParams(['site' => 'acme']))
            ->getBody();

        self::assertStringContainsString('module-docheader', $body);
        self::assertStringContainsString('<h1>x402 payment simulator</h1>', $body);
        self::assertStringContainsString('data-x402-simulator', $body);
        self::assertStringContainsString('data-url="https://acme.test/premium"', $body);
        self::assertStringContainsString('data-url="https://x402.org/facilitator/supported"', $body);
    }

    #[Test]
    public function theSimulatorRefusesPrivateHostsOutsideTheSite(): void
    {
        $controller = $this->get(PaywallSimulatorController::class);
        self::assertSame(400, $controller->runAction($this->runRequest('not json'))->getStatusCode(), 'A body that is no JSON is rejected');

        $response = $controller->runAction($this->runRequest(Json::encode(['site' => 'acme', 'scenario' => 'unpaid', 'url' => 'http://127.0.0.1/admin'])));

        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['error' => 'Only URLs of the selected site and public http(s) URLs can be requested.'], Json::decodeObject((string)$response->getBody()));
    }

    private function runRequest(string $body): ServerRequestInterface
    {
        $stream = new Stream('php://temp', 'rw');
        $stream->write($body);
        $stream->rewind();

        return $this->moduleRequest(PaywallSimulatorController::ROUTE . '.run', 'POST')->withBody($stream);
    }

    private function moduleRequest(string $routeIdentifier, string $method = 'GET'): ServerRequestInterface
    {
        $moduleIdentifier = explode('.', $routeIdentifier)[0];
        $serverParams = [
            'HTTP_HOST' => 'backend.test',
            'HTTPS' => 'on',
            'REQUEST_METHOD' => $method,
            'REQUEST_URI' => '/typo3/module/web/x402-paywall',
            'SCRIPT_NAME' => '/typo3/index.php',
            'REMOTE_ADDR' => '127.0.0.1',
        ];

        return new ServerRequest('https://backend.test' . $serverParams['REQUEST_URI'], $method, 'php://temp', [], $serverParams)
            ->withAttribute('applicationType', 2)
            ->withAttribute('normalizedParams', NormalizedParams::createFromServerParams($serverParams))
            ->withAttribute('route', new Route($serverParams['REQUEST_URI'], ['_identifier' => $routeIdentifier, 'packageName' => 'webconsulting/typo3-x402-paywall']))
            ->withAttribute('module', $this->get(ModuleProvider::class)->getModule($moduleIdentifier))
            ->withAttribute('backend.user', $GLOBALS['BE_USER']);
    }

    /**
     * @param array<string, mixed> $paywall
     */
    private function writeSiteConfiguration(string $identifier, int $rootPageId, string $base, array $paywall): void
    {
        $configuration = [
            'rootPageId' => $rootPageId,
            'base' => $base,
            'languages' => [[
                'languageId' => 0,
                'title' => 'English',
                'navigationTitle' => 'English',
                'base' => '/',
                'locale' => 'en_US.UTF-8',
                'flag' => 'us',
            ]],
            'x402_paywall' => [
                'enabled' => true,
                'wallet_address' => '0x1111111111111111111111111111111111111111',
                'facilitator_url' => 'https://x402.org/facilitator',
                'default_price' => '0.01',
                ...$paywall,
            ],
        ];

        $directory = $this->instancePath . '/typo3conf/sites/' . $identifier;
        GeneralUtility::mkdir_deep($directory);
        GeneralUtility::writeFile($directory . '/config.yaml', Yaml::dump($configuration, 99, 2), true);
    }
}
