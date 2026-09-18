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
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * Resolves the record actually being sold behind a page: detail pages of EXT:news, EXT:blog and common
 * event extensions are single TYPO3 pages with a plugin, but revenue should be attributed to the record
 * named in the plugin parameters (e.g. tx_news_pi1[news]=42). Falls back to ("page", $pageUid).
 */
final class ContentTypeResolver
{
    /**
     * Plugin namespace => [UID parameter, content type stored in tx_x402_payment_log.content_type].
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const PLUGIN_MAP = [
        'tx_news_pi1' => ['news', 'news'],
        'tx_news' => ['news', 'news'],
        'tx_blog_pi1' => ['post', 'blog_post'],
        'tx_blog' => ['post', 'blog_post'],
        'tx_cal_controller' => ['event_id', 'event'],
        'tx_seminars' => ['showUid', 'seminar'],
        'tx_events2_pi1' => ['event', 'event'],
        'tx_falkevents_pi1' => ['event', 'event'],
    ];

    /**
     * @return array{type: string, uid: int}
     */
    public function resolve(ServerRequestInterface $request, int $pageUid): array
    {
        $queryParams = $request->getQueryParams();
        foreach (self::PLUGIN_MAP as $namespace => [$uidParameter, $type]) {
            $uid = ScalarValue::int(Json::object($queryParams[$namespace] ?? null)[$uidParameter] ?? null);
            if ($uid > 0) {
                return ['type' => $type, 'uid' => $uid];
            }
        }

        return ['type' => 'page', 'uid' => $pageUid];
    }
}
