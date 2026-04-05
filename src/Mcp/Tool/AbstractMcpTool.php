<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Mcp\Tool;

abstract class AbstractMcpTool
{
    public function getSchema(): array
    {
        return [
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
    public function execute(array $args)
    {
        try {
            return new \Mcp\Types\CallToolResult([
                new \Mcp\Types\TextContent($this->doExecute($args)),
            ]);
        } catch (\Throwable $exception) {
            return new \Mcp\Types\CallToolResult([
                new \Mcp\Types\TextContent(json_encode([
                    'error' => $exception->getMessage(),
                ], JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)),
            ], true);
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
