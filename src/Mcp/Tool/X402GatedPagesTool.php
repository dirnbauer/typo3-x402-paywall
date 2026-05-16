<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Mcp\Tool;

use TYPO3\CMS\Core\Database\ConnectionPool;
use Webconsulting\X402Paywall\Utility\Json;

/**
 * MCP Tool: list all TYPO3 pages with x402 paywall enabled.
 *
 * Example agent interaction:
 *   Agent: "Which pages are behind the paywall?"
 *   Tool:  [{ uid: 5, title: "Premium Article", price: "0.05 USDC", slug: "/premium-article" }]
 */
final class X402GatedPagesTool extends AbstractMcpTool
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    public function getName(): string
    {
        return 'x402_gated_pages';
    }

    public function getDescription(): string
    {
        return 'List all TYPO3 pages that have the x402 paywall enabled. '
             . 'Returns page UID, title, slug, price (USDC), and description '
             . 'for each gated page. Use this to discover which content is monetized.';
    }

    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return $this->getInputSchema();
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
        $qb = $this->connectionPool->getQueryBuilderForTable('pages');
        $rows = $qb
            ->select('uid', 'title', 'slug', 'tx_x402_paywall_price', 'tx_x402_paywall_description')
            ->from('pages')
            ->where($qb->expr()->eq('tx_x402_paywall_enabled', 1))
            ->andWhere($qb->expr()->eq('deleted', 0))
            ->andWhere($qb->expr()->eq('hidden', 0))
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();

        $result = array_map(static fn(array $row) => [
            'uid' => is_scalar($row['uid'] ?? null) ? (int)$row['uid'] : 0,
            'title' => is_scalar($row['title'] ?? null) ? (string)$row['title'] : '',
            'slug' => is_scalar($row['slug'] ?? null) ? (string)$row['slug'] : '',
            'price' => self::priceLabel($row['tx_x402_paywall_price'] ?? null),
            'description' => is_scalar($row['tx_x402_paywall_description'] ?? null) ? (string)$row['tx_x402_paywall_description'] : '',
        ], $rows);

        return Json::encode([
            'count' => count($result),
            'pages' => $result,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    private static function priceLabel(mixed $price): string
    {
        if (!is_scalar($price) || (string)$price === '') {
            return 'default USDC';
        }

        return (string)$price . ' USDC';
    }
}
