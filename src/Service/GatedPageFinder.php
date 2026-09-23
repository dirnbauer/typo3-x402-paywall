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

use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * Finds the live, visible default-language pages that have the paywall toggle enabled.
 * Pages gated through gated_page_uids or route patterns only are not included.
 */
final readonly class GatedPageFinder
{
    public function __construct(
        private ConnectionPool $connectionPool,
    ) {}

    /**
     * @return list<array{uid: int, title: string, slug: string, price: string, description: string}>
     */
    public function findToggledPages(int $limit = 0): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder
            ->select('uid', 'title', 'slug', 'tx_x402_paywall_price', 'tx_x402_paywall_description')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('tx_x402_paywall_enabled', 1),
                $queryBuilder->expr()->eq('sys_language_uid', 0),
                $queryBuilder->expr()->eq('t3ver_wsid', 0),
            )
            ->orderBy('uid');
        if ($limit > 0) {
            $queryBuilder->setMaxResults($limit);
        }

        return array_map(static fn(array $row): array => [
            'uid' => ScalarValue::int($row['uid'] ?? null),
            'title' => ScalarValue::string($row['title'] ?? null),
            'slug' => ScalarValue::string($row['slug'] ?? null),
            'price' => ScalarValue::string($row['tx_x402_paywall_price'] ?? null),
            'description' => ScalarValue::string($row['tx_x402_paywall_description'] ?? null),
        ], $queryBuilder->executeQuery()->fetchAllAssociative());
    }
}
