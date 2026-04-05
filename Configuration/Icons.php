<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return array_map(
    static fn(string $source) => [
        'provider' => SvgIconProvider::class,
        'source' => $source,
    ],
    [
        'module-x402-paywall' => 'EXT:x402_paywall/Resources/Public/Icons/module-x402-paywall.svg',
        'x402-paywall-plugin' => 'EXT:x402_paywall/Resources/Public/Icons/plugin-x402-paywall.svg',
    ]
);
