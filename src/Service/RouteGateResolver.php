<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Service;

use Psr\Http\Message\ServerRequestInterface;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * Determines whether a given request should be gated behind x402 payment.
 */
final class RouteGateResolver
{
    public function __construct(
        private readonly RequestAttributeResolver $requestAttributeResolver,
    ) {}

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
        $pageId = $this->requestAttributeResolver->getPageUid($request);
        if ($pageId > 0 && in_array($pageId, $config->gatedPageUids, true)) {
            return true;
        }

        $pageRecord = $this->requestAttributeResolver->getPageRecord($request);
        if (ScalarValue::bool($pageRecord['tx_x402_paywall_enabled'] ?? null)) {
            return true;
        }

        return false;
    }

    /**
     * Get the price for the current request.
     */
    public function getPrice(ServerRequestInterface $request, PaywallConfiguration $config): string
    {
        // Check page-level price override
        $pageRecord = $this->requestAttributeResolver->getPageRecord($request);
        $pagePrice = ScalarValue::string($pageRecord['tx_x402_paywall_price'] ?? null);
        if ($pagePrice !== '' && $pagePrice !== '0') {
            return $pagePrice;
        }

        return $config->defaultPrice;
    }

    /**
     * Get a description for the content behind the paywall.
     */
    public function getContentDescription(ServerRequestInterface $request): string
    {
        $pageRecord = $this->requestAttributeResolver->getPageRecord($request);
        $title = ScalarValue::string($pageRecord['title'] ?? null);
        if ($title !== '') {
            return $title;
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

}
