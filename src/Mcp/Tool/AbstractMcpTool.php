<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Mcp\Tool;

use Webconsulting\X402Paywall\Utility\Json;

abstract class AbstractMcpTool
{
    /**
     * @return array<string, mixed>
     */
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
