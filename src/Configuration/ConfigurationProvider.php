<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Configuration;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

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
        if (!$site instanceof Site) {
            return new PaywallConfiguration();
        }

        return PaywallConfiguration::fromArray($this->getSettings($site));
    }

    /**
     * Get configuration for a specific site identifier.
     */
    public function getForSite(string $siteIdentifier): PaywallConfiguration
    {
        try {
            $site = $this->siteFinder->getSiteByIdentifier($siteIdentifier);
            return PaywallConfiguration::fromArray($this->getSettings($site));
        } catch (\Exception) {
            return new PaywallConfiguration();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(Site $site): array
    {
        $configuration = $site->getConfiguration();
        $settings = $configuration['x402_paywall'] ?? [];

        if (!is_array($settings)) {
            return [];
        }

        /** @var array<string, mixed> $settings */
        return $settings;
    }
}
