<?php

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$tempColumns = [
    'tx_x402_paywall_enabled' => [
        'exclude' => true,
        'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:pages.tx_x402_paywall_enabled',
        'config' => [
            'type' => 'check',
            'renderType' => 'checkboxToggle',
            'default' => 0,
        ],
    ],
    'tx_x402_paywall_price' => [
        'exclude' => true,
        'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:pages.tx_x402_paywall_price',
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
        'config' => [
            'type' => 'input',
            'size' => 50,
            'max' => 255,
            'eval' => 'trim',
            'default' => '',
            'placeholder' => 'Premium content — access requires payment',
        ],
        'displayCond' => 'FIELD:tx_x402_paywall_enabled:REQ:true',
    ],
];

ExtensionManagementUtility::addTCAcolumns('pages', $tempColumns);

ExtensionManagementUtility::addToAllTCAtypes(
    'pages',
    '--div--;x402 Paywall, tx_x402_paywall_enabled, tx_x402_paywall_price, tx_x402_paywall_description',
    '',
    'after:description'
);
