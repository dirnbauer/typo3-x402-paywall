<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Service;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Routing\PageArguments;
use TYPO3\CMS\Frontend\Page\PageInformation;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * Decides whether a frontend request is gated and reads price, description and page UID from it.
 *
 * Gating sources, in order: free_routes (always win), gated_route_patterns, gated_page_uids and the
 * page toggle tx_x402_paywall_enabled. Route patterns are exact paths, "/prefix/*" or fnmatch() globs.
 */
final readonly class RouteGateResolver
{
    public function isGated(ServerRequestInterface $request, PaywallConfiguration $config): bool
    {
        if (!$config->isValid()) {
            return false;
        }

        $path = $request->getUri()->getPath();
        foreach ($config->freeRoutes as $pattern) {
            if (self::matchesPattern($path, $pattern)) {
                return false;
            }
        }
        foreach ($config->gatedRoutePatterns as $pattern) {
            if (self::matchesPattern($path, $pattern)) {
                return true;
            }
        }

        return in_array($this->getPageUid($request), $config->gatedPageUids, true)
            || ScalarValue::bool($this->pageRecord($request)['tx_x402_paywall_enabled'] ?? null);
    }

    /**
     * Page price override, otherwise the site default.
     */
    public function getPrice(ServerRequestInterface $request, PaywallConfiguration $config): string
    {
        $pagePrice = ScalarValue::string($this->pageRecord($request)['tx_x402_paywall_price'] ?? null);

        return $pagePrice !== '' && $pagePrice !== '0' ? $pagePrice : $config->defaultPrice;
    }

    /**
     * ResourceInfo.description: the page's payment prompt, then the page title, then the request path.
     */
    public function getContentDescription(ServerRequestInterface $request): string
    {
        $pageRecord = $this->pageRecord($request);
        foreach (['tx_x402_paywall_description', 'title'] as $field) {
            $value = ScalarValue::string($pageRecord[$field] ?? null);
            if ($value !== '') {
                return $value;
            }
        }

        return $request->getUri()->getPath();
    }

    /**
     * ResourceInfo.mimeType: a regular page (type 0) renders HTML; for other page types the
     * response format is unknown before rendering, so none is announced.
     */
    public function getMimeType(ServerRequestInterface $request): string
    {
        $routing = $request->getAttribute('routing');
        $pageType = $routing instanceof PageArguments ? $routing->getPageType() : '';

        return in_array($pageType, ['', '0'], true) ? 'text/html' : '';
    }

    /**
     * Resolved page UID (0 for requests without a page).
     */
    public function getPageUid(ServerRequestInterface $request): int
    {
        $pageInformation = $request->getAttribute('frontend.page.information');
        if ($pageInformation instanceof PageInformation) {
            return $pageInformation->getId();
        }

        $routing = $request->getAttribute('routing');

        return $routing instanceof PageArguments ? $routing->getPageId() : 0;
    }

    /**
     * @return array<string|int, mixed>
     */
    private function pageRecord(ServerRequestInterface $request): array
    {
        $pageInformation = $request->getAttribute('frontend.page.information');

        return $pageInformation instanceof PageInformation ? $pageInformation->getPageRecord() : [];
    }

    private static function matchesPattern(string $path, string $pattern): bool
    {
        if ($path === $pattern) {
            return true;
        }
        if (str_ends_with($pattern, '/*')) {
            return str_starts_with($path, substr($pattern, 0, -1));
        }

        return fnmatch($pattern, $path);
    }
}
