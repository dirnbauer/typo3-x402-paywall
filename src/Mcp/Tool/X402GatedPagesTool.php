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

use Webconsulting\X402Paywall\Service\GatedPageFinder;
use Webconsulting\X402Paywall\Utility\Json;

/**
 * MCP tool "x402_gated_pages": lists TYPO3 pages with the x402 paywall toggle enabled.
 */
final class X402GatedPagesTool extends AbstractMcpTool
{
    public const string NAME = 'x402_gated_pages';

    public function __construct(
        private readonly GatedPageFinder $gatedPageFinder,
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
        $pages = $this->gatedPageFinder->findToggledPages();

        return Json::encode([
            'count' => count($pages),
            'pages' => $pages,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
