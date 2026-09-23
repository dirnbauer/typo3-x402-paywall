<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Domain\Model;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Domain\Model\ResourceInfo;

final class ResourceInfoTest extends UnitTestCase
{
    #[Test]
    public function optionalFieldsAreOmittedWhenEmptyAndRoundTrip(): void
    {
        self::assertSame(['url' => 'https://example.test/a'], new ResourceInfo('https://example.test/a')->toArray());

        $full = [
            'url' => 'https://example.test/a',
            'description' => 'Article',
            'mimeType' => 'text/html',
            'serviceName' => 'Example',
            'tags' => ['news'],
            'iconUrl' => 'https://example.test/icon.png',
        ];
        self::assertSame($full, ResourceInfo::fromArray($full)->toArray());
    }

    #[Test]
    public function labelsAndIconUrlsFollowTheSpecificationLimits(): void
    {
        self::assertTrue(ResourceInfo::isValidLabel('Market Data 24/7'));
        self::assertFalse(ResourceInfo::isValidLabel(''));
        self::assertFalse(ResourceInfo::isValidLabel(str_repeat('a', 33)));
        self::assertFalse(ResourceInfo::isValidLabel('Café'));

        self::assertTrue(ResourceInfo::isValidIconUrl('http://example.test/icon.svg'));
        self::assertFalse(ResourceInfo::isValidIconUrl('/icon.svg'));
        self::assertFalse(ResourceInfo::isValidIconUrl('data:image/png;base64,AAAA'));
        self::assertFalse(ResourceInfo::isValidIconUrl('https://example.test/' . str_repeat('a', 2048)));
    }
}
