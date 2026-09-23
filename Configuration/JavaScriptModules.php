<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

return [
    'dependencies' => ['backend', 'core'],
    'imports' => [
        '@webconsulting/x402-paywall/' => 'EXT:x402_paywall/Resources/Public/JavaScript/Backend/',
    ],
];
