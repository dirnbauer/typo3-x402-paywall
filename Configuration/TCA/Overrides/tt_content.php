<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerPlugin(
    'X402Paywall',
    'Paywall',
    'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:plugin.paywall.title',
    'x402-paywall-plugin',
);
