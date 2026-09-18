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
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Frontend\Page\PageInformation;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Service\RouteGateResolver;

final class RouteGateResolverTest extends UnitTestCase
{
    private RouteGateResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new RouteGateResolver();
    }

    #[Test]
    public function disabledConfigurationNeverGates(): void
    {
        $config = PaywallConfiguration::fromArray(['enabled' => false, 'wallet_address' => '0xabc', 'gated_route_patterns' => ['/*']]);

        self::assertFalse($this->resolver->isGated($this->request('/api/v1/content/42'), $config));
    }

    #[Test]
    public function freeRoutesOverrideGatedPatterns(): void
    {
        $config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xabc',
            'free_routes' => ['/api/v1/health', '/api/v1/content/free'],
            'gated_route_patterns' => ['/api/v1/*'],
        ]);

        self::assertFalse($this->resolver->isGated($this->request('/api/v1/health'), $config));
        self::assertFalse($this->resolver->isGated($this->request('/api/v1/content/free'), $config));
        self::assertTrue($this->resolver->isGated($this->request('/api/v1/content/42'), $config));
        self::assertFalse($this->resolver->isGated($this->request('/blog'), $config));
    }

    #[Test]
    public function globPatternsAreSupported(): void
    {
        $config = PaywallConfiguration::fromArray(['enabled' => true, 'wallet_address' => '0xabc', 'gated_route_patterns' => ['/premium-*']]);

        self::assertTrue($this->resolver->isGated($this->request('/premium-article'), $config));
        self::assertFalse($this->resolver->isGated($this->request('/free-article'), $config));
    }

    #[Test]
    public function gatedPageUidsMatchTheRoutingResult(): void
    {
        $config = PaywallConfiguration::fromArray(['enabled' => true, 'wallet_address' => '0xabc', 'gated_page_uids' => [42, 100]]);

        self::assertTrue($this->resolver->isGated($this->request('/', routing: new PageArguments(42, '0', [])), $config));
        self::assertFalse($this->resolver->isGated($this->request('/', routing: new PageArguments(43, '0', [])), $config));
    }

    #[Test]
    public function pageToggleGatesThePage(): void
    {
        $config = PaywallConfiguration::fromArray(['enabled' => true, 'wallet_address' => '0xabc']);
        $request = $this->request('/premium', pageRecord: ['uid' => 9, 'title' => 'Premium', 'tx_x402_paywall_enabled' => 1]);

        self::assertTrue($this->resolver->isGated($request, $config));
        self::assertFalse($this->resolver->isGated($this->request('/free', pageRecord: ['uid' => 10, 'tx_x402_paywall_enabled' => 0]), $config));
    }

    #[Test]
    public function priceAndDescriptionComeFromThePageRecordWithConfigurationFallback(): void
    {
        $config = PaywallConfiguration::fromArray(['enabled' => true, 'wallet_address' => '0xabc', 'default_price' => '0.05']);

        $withOverride = $this->request('/premium', pageRecord: ['uid' => 9, 'title' => 'Premium', 'tx_x402_paywall_price' => '0.25', 'tx_x402_paywall_description' => 'Full report']);
        self::assertSame('0.25', $this->resolver->getPrice($withOverride, $config));
        self::assertSame('Full report', $this->resolver->getContentDescription($withOverride));

        $withoutOverride = $this->request('/premium', pageRecord: ['uid' => 9, 'title' => 'Premium', 'tx_x402_paywall_price' => '']);
        self::assertSame('0.05', $this->resolver->getPrice($withoutOverride, $config));
        self::assertSame('Premium', $this->resolver->getContentDescription($withoutOverride));

        self::assertSame('/api/v1/content/42', $this->resolver->getContentDescription($this->request('/api/v1/content/42')));
    }

    #[Test]
    public function pageUidComesFromThePageInformationThenTheRoutingResult(): void
    {
        self::assertSame(9, $this->resolver->getPageUid($this->request('/', routing: new PageArguments(3, '0', []), pageRecord: ['uid' => 9])));
        self::assertSame(3, $this->resolver->getPageUid($this->request('/', routing: new PageArguments(3, '0', []))));
        self::assertSame(0, $this->resolver->getPageUid($this->request('/')));
    }

    /**
     * @param array<string, mixed>|null $pageRecord
     */
    private function request(string $path, ?PageArguments $routing = null, ?array $pageRecord = null): ServerRequestInterface
    {
        $request = new ServerRequest('https://example.test' . $path);
        if ($routing !== null) {
            $request = $request->withAttribute('routing', $routing);
        }
        if ($pageRecord !== null) {
            $pageInformation = new PageInformation();
            $pageInformation->setId(is_int($pageRecord['uid'] ?? null) ? $pageRecord['uid'] : 0);
            $pageInformation->setPageRecord($pageRecord);
            $request = $request->withAttribute('frontend.page.information', $pageInformation);
        }

        return $request;
    }
}
