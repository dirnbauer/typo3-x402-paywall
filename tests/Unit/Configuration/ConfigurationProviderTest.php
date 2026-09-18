<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Configuration;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Configuration\ConfigurationProvider;

final class ConfigurationProviderTest extends UnitTestCase
{
    #[Test]
    public function readsTheX402PaywallBlockOfTheRequestSite(): void
    {
        $site = new Site('main', 1, ['base' => 'https://example.test/', 'x402_paywall' => ['enabled' => true, 'wallet_address' => '0xabc', 'network' => 'base']]);
        $request = (new ServerRequest('https://example.test/'))->withAttribute('site', $site);

        $config = (new ConfigurationProvider())->getFromRequest($request);

        self::assertTrue($config->isValid());
        self::assertSame('0xabc', $config->walletAddress);
        self::assertSame('eip155:8453', $config->getCaip2NetworkId());
    }

    #[Test]
    public function requestsWithoutSiteOrBlockGetDisabledDefaults(): void
    {
        $provider = new ConfigurationProvider();

        self::assertFalse($provider->getFromRequest(new ServerRequest('https://example.test/'))->enabled);
        self::assertFalse($provider->getForSite(new Site('main', 1, ['base' => '/']))->enabled);
        self::assertFalse($provider->getForSite(new Site('main', 1, ['base' => '/', 'x402_paywall' => 'yes']))->enabled);
    }
}
