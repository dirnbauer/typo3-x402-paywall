<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Webconsulting\X402Paywall\Mcp\Tool\AbstractMcpTool;

final class AbstractMcpToolTest extends TestCase
{
    public function testExecuteReturnsSdkNeutralString(): void
    {
        $tool = new class extends AbstractMcpTool {
            public function getName(): string
            {
                return 'test';
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
                return json_encode($args, JSON_THROW_ON_ERROR);
            }
        };

        self::assertSame('{"value":"ok"}', $tool->execute(['value' => 'ok']));
    }

    public function testExecuteNormalizesExceptionsWithoutSdkTypes(): void
    {
        $tool = new class extends AbstractMcpTool {
            public function getName(): string
            {
                return 'test';
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
                throw new \RuntimeException('failed');
            }
        };

        self::assertSame([
            'error' => 'failed',
        ], json_decode($tool->execute([]), true, flags: JSON_THROW_ON_ERROR));
    }
}
