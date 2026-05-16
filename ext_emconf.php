<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'x402 Paywall',
    'description' => 'HTTP 402 payment protocol for TYPO3 14 content monetization.',
    'category' => 'plugin',
    'author' => 'Kurt Dirnbauer',
    'author_email' => 'office@webconsulting.at',
    'author_company' => 'webconsulting.at',
    'state' => 'alpha',
    'version' => '2.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '14.3.0-14.99.99',
            'php' => '8.2.0-8.5.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
