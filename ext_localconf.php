<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;
use Webconsulting\X402Paywall\Controller\PaywallController;

ExtensionUtility::configurePlugin(
    'X402Paywall',
    'Paywall',
    [
        PaywallController::class => 'show',
    ],
    // Non-cacheable actions
    [
        PaywallController::class => 'show',
    ],
);

ExtensionUtility::configurePlugin(
    'X402Paywall',
    'Verify',
    [
        PaywallController::class => 'verify',
    ],
    [
        PaywallController::class => 'verify',
    ],
);
