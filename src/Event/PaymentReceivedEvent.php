<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Event;

/**
 * Dispatched when a payment is successfully verified and settled.
 */
final class PaymentReceivedEvent
{
    public function __construct(
        public readonly string $requestUri,
        public readonly string $price,
        public readonly string $currency,
        public readonly ?string $txHash,
        public readonly string $network,
    ) {}
}
