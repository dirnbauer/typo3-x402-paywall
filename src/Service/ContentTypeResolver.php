<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Service;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the actual content type and record UID from a PSR-7 request.
 *
 * In TYPO3, a detail page for a news article or event is technically a single
 * TYPO3 page with a plugin, but the *content* being sold is the news/event
 * record — identified via URL parameters (e.g. tx_news_pi1[news]=42).
 *
 * This resolver checks well-known URL parameter patterns from common TYPO3
 * extensions and returns the content type + UID of the actual record.
 * Falls back to ("page", $pageUid) when no specific content can be detected.
 *
 * Supported out of the box:
 *   - EXT:news      → tx_news_pi1[news] / tx_news[news]
 *   - EXT:blog      → tx_blog_pi1[post] / tx_blog[post]
 *   - EXT:cal       → tx_cal_controller[event_id]
 *   - EXT:seminars  → tx_seminars[showUid]
 *   - EXT:events2   → tx_events2_pi1[event]
 *   - EXT:falkevents → tx_falkevents_pi1[event]
 *   - EXT:pw_teaser → falls back to page
 */
final class ContentTypeResolver
{
    /**
     * Maps plugin namespace → [uidParam, contentType label].
     * contentType is stored in tx_x402_payment_log.content_type.
     *
     * @var array<string, array{uidParam: string, type: string}>
     */
    private const PLUGIN_MAP = [
        'tx_news_pi1'           => ['uidParam' => 'news',     'type' => 'news'],
        'tx_news'               => ['uidParam' => 'news',     'type' => 'news'],
        'tx_blog_pi1'           => ['uidParam' => 'post',     'type' => 'blog_post'],
        'tx_blog'               => ['uidParam' => 'post',     'type' => 'blog_post'],
        'tx_cal_controller'     => ['uidParam' => 'event_id', 'type' => 'event'],
        'tx_seminars'           => ['uidParam' => 'showUid',  'type' => 'seminar'],
        'tx_events2_pi1'        => ['uidParam' => 'event',    'type' => 'event'],
        'tx_falkevents_pi1'     => ['uidParam' => 'event',    'type' => 'event'],
    ];

    /**
     * @return array{type: string, uid: int}
     */
    public function resolve(ServerRequestInterface $request, int $pageUid): array
    {
        $queryParams = $request->getQueryParams();

        foreach (self::PLUGIN_MAP as $namespace => $mapping) {
            if (!isset($queryParams[$namespace])) {
                continue;
            }

            $namespaceParams = $queryParams[$namespace];
            if (!is_array($namespaceParams)) {
                continue;
            }

            $uid = (int)($namespaceParams[$mapping['uidParam']] ?? 0);
            if ($uid > 0) {
                return ['type' => $mapping['type'], 'uid' => $uid];
            }
        }

        return ['type' => 'page', 'uid' => $pageUid];
    }
}
