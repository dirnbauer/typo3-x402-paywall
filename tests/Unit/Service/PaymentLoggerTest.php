<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Domain\Model\SettlementResponse;
use Webconsulting\X402Paywall\Service\PaymentLogger;

final class PaymentLoggerTest extends UnitTestCase
{
    /** @var array<string, mixed> */
    private array $insertedRow = [];

    private string $insertedTable = '';

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['TYPO3_CONF_VARS'] = ['SYS' => ['encryptionKey' => str_repeat('a', 64)]];
    }

    #[Test]
    public function logPaymentInsertsANormalisedRow(): void
    {
        $request = new ServerRequest('https://example.test/premium?tx_news_pi1[news]=42', 'GET', null, ['User-Agent' => 'agent/1.0'], ['REMOTE_ADDR' => '203.0.113.7']);
        $settlement = SettlementResponse::fromArray(['success' => true, 'transaction' => '0xtx', 'network' => 'eip155:84532', 'payer' => '0xPayer']);

        $this->logger()->logPayment($request, 5, 'news', 42, '0.05', 'USDC', $settlement);

        self::assertSame(PaymentLogger::TABLE, $this->insertedTable);
        $row = $this->insertedRow;
        self::assertSame(5, $row['pid']);
        self::assertSame(5, $row['page_uid']);
        self::assertSame('news', $row['content_type']);
        self::assertSame(42, $row['content_uid']);
        self::assertSame('0.05', $row['amount']);
        self::assertSame('USDC', $row['currency']);
        self::assertSame('eip155:84532', $row['network']);
        self::assertSame('0xtx', $row['tx_hash']);
        self::assertSame('0xPayer', $row['payer_address']);
        self::assertSame('{"success":true,"transaction":"0xtx","network":"eip155:84532","payer":"0xPayer"}', $row['facilitator_response']);
        self::assertSame('settled', $row['status']);
        self::assertSame('agent/1.0', $row['user_agent']);
        self::assertSame('https://example.test/premium?tx_news_pi1%5Bnews%5D=42', $row['request_uri']);
        self::assertIsString($row['ip_hash']);
        self::assertNotSame('', $row['ip_hash']);
        self::assertStringNotContainsString('203.0.113.7', $row['ip_hash']);
    }

    #[Test]
    public function failedSettlementsFallBackToThePageUidAndSkipEmptyValues(): void
    {
        $this->logger()->logPayment(new ServerRequest('https://example.test/'), 7, 'page', 0, '0.01', 'USDC', SettlementResponse::failed('facilitator_unreachable', 'eip155:8453'));

        self::assertSame(7, $this->insertedRow['content_uid']);
        self::assertSame('page', $this->insertedRow['content_type']);
        self::assertSame('eip155:8453', $this->insertedRow['network']);
        self::assertSame('', $this->insertedRow['tx_hash']);
        self::assertSame('', $this->insertedRow['payer_address']);
        self::assertSame('', $this->insertedRow['facilitator_response']);
        self::assertSame('', $this->insertedRow['ip_hash']);
        self::assertSame('failed', $this->insertedRow['status']);
    }

    private function logger(): PaymentLogger
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('insert')
            ->willReturnCallback(function (string $table, array $data): int {
                $this->insertedTable = $table;
                $this->insertedRow = [];
                foreach ($data as $key => $value) {
                    $this->insertedRow[(string)$key] = $value;
                }

                return 1;
            });
        $connectionPool = self::createStub(ConnectionPool::class);
        $connectionPool->method('getConnectionForTable')->willReturn($connection);

        return new PaymentLogger($connectionPool, new HashService());
    }
}
