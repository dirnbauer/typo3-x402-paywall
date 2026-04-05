<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log',
        'label' => 'tx_hash',
        'label_alt' => 'content_type,amount,currency',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => '',
        'default_sortby' => 'crdate DESC',
        'iconfile' => 'EXT:x402_paywall/Resources/Public/Icons/module-x402-paywall.svg',
        'readOnly' => false,
        'hideTable' => false,
        'rootLevel' => -1,
    ],
    'columns' => [
        'crdate' => [
            'label' => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.creationDate',
            'config' => [
                'type' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'page_uid' => [
            'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.page_uid',
            'config' => [
                'type' => 'group',
                'allowed' => 'pages',
                'size' => 1,
                'maxitems' => 1,
                'readOnly' => true,
            ],
        ],
        'content_type' => [
            'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.content_type',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'readOnly' => true,
            ],
        ],
        'content_uid' => [
            'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.content_uid',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'amount' => [
            'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.amount',
            'config' => [
                'type' => 'input',
                'size' => 15,
                'readOnly' => true,
            ],
        ],
        'currency' => [
            'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.currency',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'readOnly' => true,
            ],
        ],
        'network' => [
            'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.network',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'readOnly' => true,
            ],
        ],
        'status' => [
            'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.status',
            'config' => [
                'type' => 'input',
                'size' => 15,
                'readOnly' => true,
            ],
        ],
        'tx_hash' => [
            'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.tx_hash',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'readOnly' => true,
            ],
        ],
        'payer_address' => [
            'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.payer_address',
            'config' => [
                'type' => 'input',
                'size' => 50,
                'readOnly' => true,
            ],
        ],
        'request_uri' => [
            'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.request_uri',
            'config' => [
                'type' => 'text',
                'rows' => 2,
                'readOnly' => true,
            ],
        ],
        'facilitator_response' => [
            'label' => 'LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.facilitator_response',
            'config' => [
                'type' => 'text',
                'rows' => 4,
                'readOnly' => true,
            ],
        ],
    ],
    'types' => [
        '0' => [
            'showitem' => '
                --div--;LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.tab.transaction,
                    crdate, status, amount, currency, network,
                --div--;LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.tab.content,
                    page_uid, content_type, content_uid, request_uri,
                --div--;LLL:EXT:x402_paywall/Resources/Private/Language/locallang_db.xlf:tx_x402_payment_log.tab.payment,
                    tx_hash, payer_address, facilitator_response,
            ',
        ],
    ],
];
