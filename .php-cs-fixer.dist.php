<?php

declare(strict_types=1);

use TYPO3\CodingStandards\CsFixerConfig;

$config = CsFixerConfig::create();
$config->setHeader(
    'This file is part of the TYPO3 extension "x402_paywall" by webconsulting.'
    . "\n\n" . 'It is free software; you can redistribute it and/or modify it under'
    . "\n" . 'the terms of the GNU General Public License, either version 2'
    . "\n" . 'of the License, or any later version.',
    true,
);
$config->getFinder()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests')
    ->in(__DIR__ . '/Configuration')
    ->name('*.php');

return $config;
