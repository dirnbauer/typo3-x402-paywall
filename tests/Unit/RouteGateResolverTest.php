<?php

declare(strict_types=1);

namespace Webconsulting\X402Paywall\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Webconsulting\X402Paywall\Configuration\PaywallConfiguration;
use Webconsulting\X402Paywall\Service\RouteGateResolver;

final class RouteGateResolverTest extends TestCase
{
    private RouteGateResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new RouteGateResolver();
    }

    public function testDisabledConfigNeverGates(): void
    {
        $config = new PaywallConfiguration(enabled: false);
        $request = $this->createRequest('/api/v1/content/42');

        self::assertFalse($this->resolver->isGated($request, $config));
    }

    public function testFreeRouteIsNotGated(): void
    {
        $config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xabc',
            'free_routes' => ['/api/v1/health', '/'],
            'gated_route_patterns' => ['/api/v1/*'],
        ]);

        $request = $this->createRequest('/api/v1/health');
        self::assertFalse($this->resolver->isGated($request, $config));
    }

    public function testGatedPatternMatches(): void
    {
        $config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xabc',
            'gated_route_patterns' => ['/api/v1/content/*'],
        ]);

        $request = $this->createRequest('/api/v1/content/42');
        self::assertTrue($this->resolver->isGated($request, $config));
    }

    public function testGatedPatternDoesNotMatchOtherRoutes(): void
    {
        $config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xabc',
            'gated_route_patterns' => ['/api/v1/content/*'],
        ]);

        $request = $this->createRequest('/api/v1/navigation');
        self::assertFalse($this->resolver->isGated($request, $config));
    }

    public function testFreeRouteOverridesGatedPattern(): void
    {
        $config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xabc',
            'free_routes' => ['/api/v1/content/free'],
            'gated_route_patterns' => ['/api/v1/content/*'],
        ]);

        $request = $this->createRequest('/api/v1/content/free');
        self::assertFalse($this->resolver->isGated($request, $config));
    }

    public function testGatedPageUidMatches(): void
    {
        $config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xabc',
            'gated_page_uids' => [42, 100],
        ]);

        $request = $this->createRequest('/', ['id' => '42']);
        self::assertTrue($this->resolver->isGated($request, $config));
    }

    public function testDefaultPriceUsedWhenNoOverride(): void
    {
        $config = PaywallConfiguration::fromArray([
            'enabled' => true,
            'wallet_address' => '0xabc',
            'default_price' => '0.05',
        ]);

        $request = $this->createRequest('/api/v1/content/42');
        self::assertSame('0.05', $this->resolver->getPrice($request, $config));
    }

    private function createRequest(string $path, array $queryParams = []): ServerRequestInterface
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('__toString')->willReturn('https://example.com' . $path);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getUri')->willReturn($uri);
        $request->method('getQueryParams')->willReturn($queryParams);
        $request->method('getAttribute')->willReturn(null);

        return $request;
    }
}
