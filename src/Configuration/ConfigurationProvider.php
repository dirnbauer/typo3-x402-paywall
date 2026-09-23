<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Configuration;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use Webconsulting\X402Paywall\Utility\Json;

/**
 * Reads the "x402_paywall" block of a site configuration into a PaywallConfiguration.
 */
final readonly class ConfigurationProvider
{
    /**
     * Configuration of the site the request was routed to; disabled defaults without a site.
     */
    public function getFromRequest(ServerRequestInterface $request): PaywallConfiguration
    {
        $site = $request->getAttribute('site');

        return $site instanceof Site ? $this->getForSite($site) : new PaywallConfiguration();
    }

    public function getForSite(Site $site): PaywallConfiguration
    {
        return PaywallConfiguration::fromArray(Json::object($site->getConfiguration()['x402_paywall'] ?? null));
    }
}
