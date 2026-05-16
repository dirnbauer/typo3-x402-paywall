<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

ExtensionUtility::registerPlugin(
    'X402Paywall',
    'Paywall',
    'x402 Paywall Overlay',
    'x402-paywall-plugin',
);
