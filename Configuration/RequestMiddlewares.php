<?php

return [
    'frontend' => [
        'webconsulting/x402-paywall' => [
            'target' => \Webconsulting\X402Paywall\Middleware\X402PaywallMiddleware::class,
            'description' => 'x402 payment protocol — intercepts requests to gated content and requires payment',
            'before' => [
                'typo3/cms-frontend/content-length-headers',
            ],
            'after' => [
                'typo3/cms-frontend/site',
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
