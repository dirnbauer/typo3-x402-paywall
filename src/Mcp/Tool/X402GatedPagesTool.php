<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Mcp\Tool;

use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\X402Paywall\Utility\Json;
use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * MCP tool "x402_gated_pages": lists TYPO3 pages with the x402 paywall toggle enabled.
 */
final class X402GatedPagesTool extends AbstractMcpTool
{
    public const NAME = 'x402_gated_pages';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function getName(): string
    {
        return self::NAME;
    }

    public function getDescription(): string
    {
        return 'List all TYPO3 pages that have the x402 paywall enabled through the page properties. '
            . 'Returns page UID, title, slug, price override (empty = site default price) and the payment '
            . 'prompt description. Pages gated only through route patterns or gated_page_uids are not listed.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [],
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    protected function doExecute(array $args): string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $rows = $queryBuilder
            ->select('uid', 'title', 'slug', 'tx_x402_paywall_price', 'tx_x402_paywall_description')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('tx_x402_paywall_enabled', 1),
                $queryBuilder->expr()->eq('deleted', 0),
                $queryBuilder->expr()->eq('hidden', 0),
                $queryBuilder->expr()->eq('sys_language_uid', 0),
            )
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        $pages = array_map(static fn(array $row): array => [
            'uid' => ScalarValue::int($row['uid'] ?? null),
            'title' => ScalarValue::string($row['title'] ?? null),
            'slug' => ScalarValue::string($row['slug'] ?? null),
            'price' => ScalarValue::string($row['tx_x402_paywall_price'] ?? null),
            'description' => ScalarValue::string($row['tx_x402_paywall_description'] ?? null),
        ], $rows);

        return Json::encode([
            'count' => count($pages),
            'pages' => $pages,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
