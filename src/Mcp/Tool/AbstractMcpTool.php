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

use Webconsulting\X402Paywall\Utility\Json;

/**
 * Base class of the SDK-neutral MCP tools. Tools are tagged "mcp.tool" and picked up by
 * hn/typo3-mcp-server, which converts the returned string into the installed SDK's result type.
 */
abstract class AbstractMcpTool
{
    /**
     * @return array<string, mixed>
     */
    public function getSchema(): array
    {
        return [
            'name' => $this->getName(),
            'description' => $this->getDescription(),
            'inputSchema' => $this->getInputSchema(),
            'annotations' => [
                'readOnlyHint' => true,
                'destructiveHint' => false,
                'idempotentHint' => true,
                'openWorldHint' => true,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $args
     */
    public function execute(array $args): string
    {
        try {
            return $this->doExecute($args);
        } catch (\Throwable $exception) {
            return Json::encode([
                'error' => $exception->getMessage(),
            ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        }
    }

    abstract public function getName(): string;

    abstract public function getDescription(): string;

    /**
     * @return array<string, mixed>
     */
    abstract public function getInputSchema(): array;

    /**
     * @param array<string, mixed> $args
     */
    abstract protected function doExecute(array $args): string;
}
