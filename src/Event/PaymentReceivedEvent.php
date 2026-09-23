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
final readonly class PaymentReceivedEvent
{
    public function __construct(
        public string $requestUri,
        public string $price,
        public string $currency,
        public ?string $txHash,
        public string $network,
        public string $payer = '',
    ) {}
}
