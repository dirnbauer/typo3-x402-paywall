<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 extension "x402_paywall" by webconsulting.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 */

namespace Webconsulting\X402Paywall\Tests\Unit\Utility;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;
use Webconsulting\X402Paywall\Utility\HttpUrl;

final class HttpUrlTest extends UnitTestCase
{
    #[Test]
    public function allowsPublicHttpIp(): void
    {
        self::assertTrue(HttpUrl::isAllowedOutboundHttpUrl('https://93.184.216.34/premium'));
    }

    #[Test]
    public function rejectsNonHttpSchemes(): void
    {
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('file:///etc/passwd'));
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('ftp://93.184.216.34/'));
    }

    #[Test]
    public function rejectsLocalhostAndUnresolvableHosts(): void
    {
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('http://localhost/premium'));
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('http://foo.localhost/premium'));
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('https://does-not-exist.invalid/premium'));
    }

    #[Test]
    public function rejectsPrivateAndReservedIpRanges(): void
    {
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('http://127.0.0.1/status'));
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('http://10.0.0.1/status'));
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('http://192.168.0.10/status'));
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('http://169.254.169.254/latest/meta-data'));
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('http://[::1]/status'));
    }
}
