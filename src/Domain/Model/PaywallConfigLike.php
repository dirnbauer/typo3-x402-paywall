<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Domain\Model;

/**
 * Interface so PaymentRequirement can work with both PaywallConfiguration and test doubles.
 */
interface PaywallConfigLike
{
    public function getCaip2NetworkId(): string;
    public function getWalletAddress(): string;
    public function getCurrency(): string;
    public function getNetwork(): string;
}
