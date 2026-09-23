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
use Webconsulting\X402Paywall\Domain\Model\VerifyResponse;

final class VerifyResponseTest extends UnitTestCase
{
    #[Test]
    public function validResponsesCarryThePayer(): void
    {
        $response = VerifyResponse::fromArray(['isValid' => true, 'payer' => '0xPayer', 'invalidReason' => 'ignored']);

        self::assertTrue($response->isValid);
        self::assertSame('0xPayer', $response->payer);
        self::assertSame('', $response->invalidReason);
    }

    #[Test]
    public function invalidResponsesKeepReasonCodeAndMessageApart(): void
    {
        $rejected = VerifyResponse::fromArray(['isValid' => false, 'invalidReason' => 'insufficient_funds', 'invalidMessage' => 'Balance too low']);
        self::assertSame('insufficient_funds', $rejected->invalidReason);
        self::assertSame('Balance too low', $rejected->invalidMessage);

        $apiError = VerifyResponse::fromArray(['error' => 'bad request']);
        self::assertSame('unexpected_verify_error', $apiError->invalidReason);
        self::assertSame('bad request', $apiError->invalidMessage);

        self::assertSame('unexpected_verify_error', VerifyResponse::fromArray(['isValid' => 'true'])->invalidReason);
        self::assertSame('invalid_payload', VerifyResponse::invalid('invalid_payload')->invalidReason);
    }
}
