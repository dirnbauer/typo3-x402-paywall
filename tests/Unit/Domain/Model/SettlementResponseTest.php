<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Domain\Model\SettlementResponse;

final class SettlementResponseTest extends UnitTestCase
{
    #[Test]
    public function successfulSettlementsFollowTheV2WireFormat(): void
    {
        $body = ['success' => true, 'transaction' => '0xtx', 'network' => 'eip155:84532', 'payer' => '0xPayer', 'amount' => '10000', 'extra' => 'ignored'];

        $settlement = SettlementResponse::fromArray($body);

        self::assertTrue($settlement->success);
        self::assertSame('', $settlement->errorReason);
        self::assertSame($body, $settlement->raw);
        self::assertSame(['success' => true, 'transaction' => '0xtx', 'network' => 'eip155:84532', 'payer' => '0xPayer', 'amount' => '10000'], $settlement->toArray());
        self::assertSame($settlement->toArray(), SettlementResponse::fromHeaderValue($settlement->toHeaderValue())->toArray());
    }

    #[Test]
    public function defaultsFillMissingNetworkAndPayer(): void
    {
        $settlement = SettlementResponse::fromArray(['success' => true, 'transaction' => '0xtx'], 'eip155:8453', '0xSigner');

        self::assertSame('eip155:8453', $settlement->network);
        self::assertSame('0xSigner', $settlement->payer);
        self::assertSame(['success' => true, 'transaction' => '0xtx', 'network' => 'eip155:8453', 'payer' => '0xSigner'], $settlement->toArray());
    }

    #[Test]
    public function failuresCarryTheErrorReasonAndMessage(): void
    {
        self::assertSame('invalid_transaction_state', SettlementResponse::fromArray(['success' => false, 'errorReason' => 'invalid_transaction_state'])->errorReason);

        $apiError = SettlementResponse::fromArray(['success' => 'no', 'errorType' => 'internal_server_error', 'errorMessage' => 'boom']);
        self::assertSame('unexpected_settle_error', $apiError->errorReason);
        self::assertSame('boom', $apiError->errorMessage);
        self::assertSame('unexpected_settle_error', SettlementResponse::fromArray([])->errorReason);

        $failed = SettlementResponse::failed('insufficient_funds', 'eip155:84532', errorMessage: 'Balance too low');
        self::assertFalse($failed->success);
        self::assertFalse($failed->isPending());
        self::assertSame(
            ['success' => false, 'errorReason' => 'insufficient_funds', 'errorMessage' => 'Balance too low', 'transaction' => '', 'network' => 'eip155:84532'],
            $failed->toArray(),
        );
    }

    #[Test]
    public function settlementPendingNeedsTheTransactionHash(): void
    {
        $pending = SettlementResponse::fromArray(['success' => false, 'errorReason' => 'settlement_pending', 'transaction' => '0xtx', 'network' => 'eip155:8453']);
        $withoutHash = SettlementResponse::fromArray(['success' => false, 'errorReason' => 'settlement_pending', 'transaction' => '', 'network' => 'eip155:8453']);

        self::assertTrue($pending->isSettlementPending());
        self::assertTrue($pending->isPending());
        self::assertFalse($withoutHash->isSettlementPending());
        self::assertFalse($withoutHash->isPending());
    }

    #[Test]
    public function unknownOutcomesArePendingButNotSentAsSettlementPending(): void
    {
        $unknown = SettlementResponse::outcomeUnknown('eip155:8453', '0xPayer', 'No answer');

        self::assertTrue($unknown->isPending());
        self::assertFalse($unknown->isSettlementPending());
        self::assertSame('unexpected_settle_error', $unknown->toArray()['errorReason'] ?? '');
        self::assertArrayNotHasKey('outcomeUnknown', $unknown->toArray());
    }

    #[Test]
    public function extensionsArePassedThroughAsObject(): void
    {
        $settlement = SettlementResponse::fromArray(['success' => true, 'transaction' => '0xtx', 'network' => 'eip155:8453', 'extensions' => ['builder-code' => ['s' => ['abc']]]]);

        self::assertSame('{"success":true,"transaction":"0xtx","network":"eip155:8453","extensions":{"builder-code":{"s":["abc"]}}}', (string)base64_decode($settlement->toHeaderValue(), true));
    }

    #[Test]
    public function invalidHeaderValuesThrow(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        SettlementResponse::fromHeaderValue(base64_encode('not json'));
    }
}
