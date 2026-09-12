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
    'web_x402_paywall' => [
        'parent' => 'web',
        'position' => ['after' => 'web_info'],
        'access' => 'admin',
        'workspaces' => '*',
        'path' => '/module/web/x402-paywall',
        'iconIdentifier' => 'module-x402-paywall',
        'labels' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => PaywallDashboardController::class . '::mainAction',
            ],
            'simulator' => [
                'target' => PaywallDashboardController::class . '::simulatorAction',
            ],
            'runSimulation' => [
                'target' => PaywallDashboardController::class . '::runSimulationAction',
                'methods' => ['POST'],
            ],
        ],
    ],
];
