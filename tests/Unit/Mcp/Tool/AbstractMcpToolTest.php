<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Mcp\Tool;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Mcp\Tool\AbstractMcpTool;
use Webconsulting\X402Paywall\Tests\Unit\JsonTestTrait;

final class AbstractMcpToolTest extends UnitTestCase
{
    use JsonTestTrait;

    #[Test]
    public function executeReturnsSdkNeutralString(): void
    {
        $tool = $this->tool(static fn(array $args): string => json_encode($args, JSON_THROW_ON_ERROR));

        self::assertSame('{"value":"ok"}', $tool->execute(['value' => 'ok']));
    }

    #[Test]
    public function executeNormalisesExceptionsIntoJsonErrors(): void
    {
        $tool = $this->tool(static function (array $args): string {
            throw new \RuntimeException('failed');
        });

        self::assertSame(['error' => 'failed'], self::decodeJsonObject($tool->execute([])));
    }

    #[Test]
    public function schemaContainsNameDescriptionInputSchemaAndReadOnlyAnnotations(): void
    {
        $schema = $this->tool(static fn(array $args): string => '')->getSchema();

        self::assertSame('test_tool', $schema['name']);
        self::assertSame('Test tool', $schema['description']);
        self::assertSame(['type' => 'object', 'properties' => []], $schema['inputSchema']);
        self::assertTrue(self::jsonPath($schema, 'annotations', 'readOnlyHint'));
        self::assertFalse(self::jsonPath($schema, 'annotations', 'destructiveHint'));
    }

    /**
     * @param \Closure(array<string, mixed>): string $execute
     */
    private function tool(\Closure $execute): AbstractMcpTool
    {
        return new class ($execute) extends AbstractMcpTool {
            /**
             * @param \Closure(array<string, mixed>): string $execute
             */
            public function __construct(private readonly \Closure $execute) {}

            public function getName(): string
            {
                return 'test_tool';
            }

            public function getDescription(): string
            {
                return 'Test tool';
            }

            public function getInputSchema(): array
            {
                return ['type' => 'object', 'properties' => []];
            }

            protected function doExecute(array $args): string
            {
                return ($this->execute)($args);
            }
        };
    }
}
