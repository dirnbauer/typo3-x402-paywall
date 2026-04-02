<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Event;

/**
 * Dispatched when a 402 Payment Required response is sent.
 */
final class PaymentRequiredEvent
{
    public function __construct(
        public readonly string $requestUri,
        public readonly string $price,
        public readonly string $currency,
    ) {}
}
