<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use Webconsulting\X402Paywall\Controller\PaywallDashboardController;

return [
    'x402_paywall_stats' => [
        'path' => '/x402-paywall/stats',
        'target' => PaywallDashboardController::class . '::statsAction',
    ],
];
