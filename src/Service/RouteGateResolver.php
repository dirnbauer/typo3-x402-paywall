<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Service;

use Psr\Http\Message\ServerRequestInterface;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;

/**
 * Determines whether a given request should be gated behind x402 payment.
 */
final class RouteGateResolver
{
    /**
     * Check if the current request requires payment.
     */
    public function isGated(ServerRequestInterface $request, PaywallConfiguration $config): bool
    {
        if (!$config->isValid()) {
            return false;
        }

        $path = $request->getUri()->getPath();

        // Check free routes first (whitelist takes priority)
        foreach ($config->freeRoutes as $freeRoute) {
            if ($this->matchesPattern($path, $freeRoute)) {
                return false;
            }
        }

        // Check gated route patterns (for headless/API mode)
        foreach ($config->gatedRoutePatterns as $pattern) {
            if ($this->matchesPattern($path, $pattern)) {
                return true;
            }
        }

        // Check page UID (for traditional TYPO3 frontend)
        $pageId = $this->getPageIdFromRequest($request);
        if ($pageId !== null && in_array($pageId, $config->gatedPageUids, true)) {
            return true;
        }

        // Check page TSconfig / TCA field (future: per-page toggle in backend)
        $page = $request->getAttribute('frontend.page.information');
        if ($page !== null) {
            $pageRecord = $page->getPageRecord() ?? [];
            if (($pageRecord['tx_x402_paywall_enabled'] ?? false)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the price for the current request.
     */
    public function getPrice(ServerRequestInterface $request, PaywallConfiguration $config): string
    {
        // Check page-level price override
        $page = $request->getAttribute('frontend.page.information');
        if ($page !== null) {
            $pageRecord = $page->getPageRecord() ?? [];
            $pagePrice = $pageRecord['tx_x402_paywall_price'] ?? '';
            if ($pagePrice !== '' && $pagePrice !== '0') {
                return $pagePrice;
            }
        }

        return $config->defaultPrice;
    }

    /**
     * Get a description for the content behind the paywall.
     */
    public function getContentDescription(ServerRequestInterface $request): string
    {
        $page = $request->getAttribute('frontend.page.information');
        if ($page !== null) {
            $pageRecord = $page->getPageRecord() ?? [];
            return $pageRecord['title'] ?? $request->getUri()->getPath();
        }

        return $request->getUri()->getPath();
    }

    private function matchesPattern(string $path, string $pattern): bool
    {
        // Exact match
        if ($path === $pattern) {
            return true;
        }

        // Wildcard: /api/v1/content/* matches /api/v1/content/42
        if (str_ends_with($pattern, '/*')) {
            $prefix = substr($pattern, 0, -1);
            return str_starts_with($path, $prefix);
        }

        // Glob pattern
        return fnmatch($pattern, $path);
    }

    private function getPageIdFromRequest(ServerRequestInterface $request): ?int
    {
        // TYPO3 frontend: page ID from routing
        $routing = $request->getAttribute('routing');
        if ($routing !== null && method_exists($routing, 'getPageId')) {
            return $routing->getPageId();
        }

        // Fallback: query parameter
        $params = $request->getQueryParams();
        if (isset($params['id'])) {
            return (int)$params['id'];
        }

        return null;
    }
}
