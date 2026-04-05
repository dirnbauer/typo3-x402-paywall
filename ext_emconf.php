<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'x402 Paywall',
    'description' => 'x402 payment protocol for TYPO3 - charge AI agents and users for content access via HTTP 402.',
    'category' => 'plugin',
    'author' => 'Kurt Dirnbauer',
    'author_email' => 'office@webconsulting.at',
    'author_company' => 'webconsulting.at',
    'state' => 'stable',
    'version' => '1.2.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'php' => '8.2.0-8.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
