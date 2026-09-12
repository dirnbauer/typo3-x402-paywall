<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use Webconsulting\X402Paywall\Middleware\X402PaywallMiddleware;

return [
    'frontend' => [
        'webconsulting/x402-paywall' => [
            'target' => X402PaywallMiddleware::class,
            'description' => 'x402 payment protocol: answers 402 Payment Required for gated pages and routes and verifies PAYMENT-SIGNATURE headers',
            // Runs once the page record is resolved (frontend.page.information) so the page toggle is visible,
            // and after shortcut/mount point redirects so those never reach the paywall.
            'after' => [
                'typo3/cms-frontend/prepare-tsfe-rendering',
                'typo3/cms-frontend/shortcut-and-mountpoint-redirect',
            ],
            'before' => [
                'typo3/cms-frontend/csp-headers',
            ],
        ],
    ],
];
