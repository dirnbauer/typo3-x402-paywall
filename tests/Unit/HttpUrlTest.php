<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Webconsulting\X402Paywall\Utility\HttpUrl;

final class HttpUrlTest extends TestCase
{
    public function testAllowsPublicHttpIp(): void
    {
        self::assertTrue(HttpUrl::isAllowedOutboundHttpUrl('https://93.184.216.34/premium'));
    }

    public function testRejectsNonHttpSchemes(): void
    {
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('file:///etc/passwd'));
    }

    public function testRejectsLocalhost(): void
    {
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('http://localhost/premium'));
    }

    public function testRejectsUnresolvedHostnames(): void
    {
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('https://does-not-exist.invalid/premium'));
    }

    public function testRejectsPrivateIpRanges(): void
    {
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('http://127.0.0.1/status'));
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('http://10.0.0.1/status'));
        self::assertFalse(HttpUrl::isAllowedOutboundHttpUrl('http://192.168.0.10/status'));
    }
}
