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
use Webconsulting\X402Paywall\Controller\PaywallSimulatorController;

/*
 * "Content > x402 Paywall" with two submodules. The payment log and the site
 * configuration are not page-specific, so the module works without the page tree.
 */
return [
    'web_x402_paywall' => [
        'parent' => 'content',
        'position' => ['after' => 'content_status'],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/web/x402-paywall',
        'iconIdentifier' => 'module-x402-paywall',
        'labels' => 'x402_paywall.modules.paywall',
        'inheritNavigationComponentFromMainModule' => false,
        'appearance' => [
            'dependsOnSubmodules' => true,
        ],
    ],
    'web_x402_paywall_dashboard' => [
        'parent' => 'web_x402_paywall',
        'position' => ['before' => '*'],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/web/x402-paywall/dashboard',
        'iconIdentifier' => 'module-dashboard',
        'labels' => 'x402_paywall.modules.dashboard',
        'routes' => [
            '_default' => [
                'target' => PaywallDashboardController::class . '::mainAction',
            ],
        ],
    ],
    'web_x402_paywall_simulator' => [
        'parent' => 'web_x402_paywall',
        'position' => ['after' => 'web_x402_paywall_dashboard'],
        'access' => 'admin',
        'workspaces' => 'live',
        'path' => '/module/web/x402-paywall/simulator',
        'iconIdentifier' => 'module-debug',
        'labels' => 'x402_paywall.modules.simulator',
        'routes' => [
            '_default' => [
                'target' => PaywallSimulatorController::class . '::mainAction',
            ],
            'run' => [
                'target' => PaywallSimulatorController::class . '::runAction',
                'methods' => ['POST'],
            ],
        ],
    ],
];
