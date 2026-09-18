<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Domain\Model;

use Webconsulting\X402Paywall\Utility\ScalarValue;

/**
 * x402 v2 "VerifyResponse": the facilitator's answer to POST /verify.
 */
final readonly class VerifyResponse
{
    public function __construct(
        public bool $isValid,
        public string $payer = '',
        public string $invalidReason = '',
    ) {}

    /**
     * @param array<string, mixed> $body Facilitator response
     */
    public static function fromArray(array $body): self
    {
        $isValid = ($body['isValid'] ?? null) === true;

        return new self(
            isValid: $isValid,
            payer: ScalarValue::string($body['payer'] ?? null),
            invalidReason: $isValid ? '' : ScalarValue::string($body['invalidReason'] ?? $body['error'] ?? $body['message'] ?? null, 'verification_failed'),
        );
    }

    public static function invalid(string $reason): self
    {
        return new self(isValid: false, invalidReason: $reason);
    }
}
