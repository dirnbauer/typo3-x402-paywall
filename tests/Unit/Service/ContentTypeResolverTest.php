<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Service\ContentTypeResolver;

final class ContentTypeResolverTest extends UnitTestCase
{
    #[Test]
    public function detectsNewsRecordsFromPluginParameters(): void
    {
        $request = (new ServerRequest('https://example.test/news/detail'))->withQueryParams(['tx_news_pi1' => ['news' => '42', 'action' => 'detail']]);

        self::assertSame(['type' => 'news', 'uid' => 42], (new ContentTypeResolver())->resolve($request, 7));
    }

    #[Test]
    public function fallsBackToThePage(): void
    {
        $request = (new ServerRequest('https://example.test/premium'))->withQueryParams(['tx_news_pi1' => 'garbage']);

        self::assertSame(['type' => 'page', 'uid' => 7], (new ContentTypeResolver())->resolve($request, 7));
    }
}
