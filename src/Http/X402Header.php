<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Http;

/**
 * Header names of the x402 v2 HTTP transport. Every value is a base64-encoded JSON document.
 */
final class X402Header
{
    /** Server -> client: PaymentRequired document. */
    public const string PAYMENT_REQUIRED = 'PAYMENT-REQUIRED';

    /** Client -> server: PaymentPayload document. */
    public const string PAYMENT_SIGNATURE = 'PAYMENT-SIGNATURE';

    /** Server -> client: SettlementResponse document. */
    public const string PAYMENT_RESPONSE = 'PAYMENT-RESPONSE';
}
