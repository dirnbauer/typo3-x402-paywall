<?php

declare(strict_types=1);

use Webconsulting\X402Paywall\Controller\PaywallDashboardController;

return [
    'x402_paywall_stats' => [
        'path' => '/x402-paywall/stats',
        'target' => PaywallDashboardController::class . '::statsAction',
    ],
];
