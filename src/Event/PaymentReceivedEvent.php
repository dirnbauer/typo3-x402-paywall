<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Event;

/**
 * Dispatched after a payment was verified and settled by the facilitator and the content is served.
 */
final class PaymentReceivedEvent
{
    public function __construct(
        public readonly string $requestUri,
        public readonly string $price,
        public readonly string $currency,
        public readonly ?string $txHash,
        public readonly string $network,
        public readonly string $payer = '',
    ) {}
}
