<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services\Logging;

use App\Infrastructure\Services\Logging\HttpLogContextProvider;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

final class HttpLogContextProviderTest extends TestCase
{
    public function test_returns_null_values_when_no_request(): void
    {
        $provider = new HttpLogContextProvider(null);

        $this->assertNull($provider->getRequestId());
        $this->assertNull($provider->getClientIp());
        $this->assertNull($provider->getUserAgent());
        $this->assertNull($provider->getRequestUrl());
        $this->assertSame([], $provider->getAdditionalContext());
    }

    public function test_returns_request_id_from_header(): void
    {
        $request = Request::create('/test');
        $request->headers->set('X-Request-ID', 'test-request-id-123');

        $provider = new HttpLogContextProvider($request);

        $this->assertSame('test-request-id-123', $provider->getRequestId());
    }

    public function test_returns_null_when_request_id_header_missing(): void
    {
        $request = Request::create('/test');

        $provider = new HttpLogContextProvider($request);

        $this->assertNull($provider->getRequestId());
    }

    public function test_returns_client_ip(): void
    {
        $request = Request::create('/test', 'GET', [], [], [], ['REMOTE_ADDR' => '192.168.1.100']);

        $provider = new HttpLogContextProvider($request);

        $this->assertSame('192.168.1.100', $provider->getClientIp());
    }

    public function test_returns_user_agent(): void
    {
        $request = Request::create('/test');
        $request->headers->set('User-Agent', 'Mozilla/5.0 Test Agent');

        $provider = new HttpLogContextProvider($request);

        $this->assertSame('Mozilla/5.0 Test Agent', $provider->getUserAgent());
    }

    public function test_returns_request_url(): void
    {
        $request = Request::create('/api/users', 'GET', ['page' => '1']);

        $provider = new HttpLogContextProvider($request);

        $this->assertSame('http://localhost/api/users?page=1', $provider->getRequestUrl());
    }

    public function test_returns_additional_context_with_method(): void
    {
        $request = Request::create('/test', 'POST');

        $provider = new HttpLogContextProvider($request);
        $context = $provider->getAdditionalContext();

        $this->assertSame('POST', $context['method']);
    }

    public function test_returns_additional_context_with_route_name(): void
    {
        $request = Request::create('/events/123');
        $route = new Route(['GET'], '/events/{id}', fn () => 'test');
        $route->name('events.show');
        $request->setRouteResolver(fn () => $route);

        $provider = new HttpLogContextProvider($request);
        $context = $provider->getAdditionalContext();

        $this->assertSame('events.show', $context['route']);
    }
}
