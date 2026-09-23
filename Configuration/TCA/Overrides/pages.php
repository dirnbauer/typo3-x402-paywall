<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$tempColumns = [
    'tx_x402_paywall_enabled' => [
        'exclude' => true,
        'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:pages.tx_x402_paywall_enabled',
        'description' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:pages.tx_x402_paywall_enabled.description',
        'onChange' => 'reload',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 0,
        ],
    ],
    'tx_x402_paywall_price' => [
        'exclude' => true,
        'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:pages.tx_x402_paywall_price',
        'description' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:pages.tx_x402_paywall_price.description',
        'config' => [
            'type' => 'input',
            'size' => 10,
            'eval' => 'trim',
            'default' => '',
            'placeholder' => '0.01',
        ],
        'displayCond' => 'FIELD:tx_x402_paywall_enabled:REQ:true',
    ],
    'tx_x402_paywall_description' => [
        'exclude' => true,
        'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:pages.tx_x402_paywall_description',
        'description' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:pages.tx_x402_paywall_description.description',
        'config' => [
            'type' => 'input',
            'size' => 50,
            'max' => 255,
            'eval' => 'trim',
            'default' => '',
            'placeholder' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:pages.tx_x402_paywall_description.placeholder',
        ],
        'displayCond' => 'FIELD:tx_x402_paywall_enabled:REQ:true',
    ],
];

ExtensionManagementUtility::addTCAcolumns('pages', $tempColumns);

ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    '--div--;LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:pages.tab.x402_paywall, tx_x402_paywall_enabled, tx_x402_paywall_price, tx_x402_paywall_description'
);
