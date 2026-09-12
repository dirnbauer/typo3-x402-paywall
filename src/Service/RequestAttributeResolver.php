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
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * Reads TYPO3 frontend request attributes through their v14 API objects.
 */
final class RequestAttributeResolver
{
    /**
     * @return array<string|int, mixed>
     */
    public function getPageRecord(ServerRequestInterface $request): array
    {
        $pageInformation = $request->getAttribute('frontend.page.information');
        if ($pageInformation instanceof PageInformation) {
            return $pageInformation->getPageRecord();
        }

        return [];
    }

    public function getPageUid(ServerRequestInterface $request): int
    {
        $pageInformation = $request->getAttribute('frontend.page.information');
        if ($pageInformation instanceof PageInformation) {
            return $pageInformation->getId();
        }

        $routing = $request->getAttribute('routing');
        if ($routing instanceof PageArguments) {
            return $routing->getPageId();
        }

        $pageRecord = $this->getPageRecord($request);
        if (isset($pageRecord['uid'])) {
            return ScalarValue::int($pageRecord['uid']);
        }

        return 0;
    }
}
