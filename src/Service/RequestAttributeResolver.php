<?php

declare(strict_types=1);

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
