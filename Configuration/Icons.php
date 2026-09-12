<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'module-x402-paywall' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:x402_paywall/Resources/Public/Icons/module-x402-paywall.svg',
    ],
];
