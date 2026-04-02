<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Configuration;

use TYPO3\CMS\Core\Site\SiteFinder;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Provides PaywallConfiguration from TYPO3 site settings.
 */
final class ConfigurationProvider
{
    public function __construct(
        private readonly SiteFinder $siteFinder,
    ) {}

    /**
     * Get configuration from the current request's site.
     */
    public function getFromRequest(ServerRequestInterface $request): PaywallConfiguration
    {
        $site = $request->getAttribute('site');
        if ($site === null) {
            return new PaywallConfiguration();
        }

        $settings = $site->getConfiguration()['x402_paywall'] ?? [];
        return PaywallConfiguration::fromArray($settings);
    }

    /**
     * Get configuration for a specific site identifier.
     */
    public function getForSite(string $siteIdentifier): PaywallConfiguration
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
            $settings = $site->getConfiguration()['x402_paywall'] ?? [];
            return PaywallConfiguration::fromArray($settings);
        } catch (\Exception) {
            return new PaywallConfiguration();
        }
    }
}
