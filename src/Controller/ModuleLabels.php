<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Controller;

use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Labels of the backend module (translation domain "x402_paywall.mod", locallang_mod.xlf)
 * in the language of the current backend user.
 */
final readonly class ModuleLabels
{
    public const string DOMAIN = 'x402_paywall.mod';

    public function __construct(
        private LanguageServiceFactory $languageServiceFactory,
    ) {}

    /**
     * @param array<array-key, mixed> $arguments
     */
    public function get(string $key, array $arguments = []): string
    {
        $label = (string)$this->languageServiceFactory
            ->createFromUserPreferences($GLOBALS['BE_USER'] ?? null)
            ->translate($key, self::DOMAIN, $arguments);

        return $label !== '' ? $label : $key;
    }
}
