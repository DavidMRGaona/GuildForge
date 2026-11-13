<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use App\Application\Security\Services\ContentSecurityPolicySourcesInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\Support\Realtime\EnablesRealtime;
use Tests\TestCase;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    use EnablesRealtime;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a test route with web middleware (which includes security headers)
        Route::get('/test-security-headers', fn () => response()->json(['message' => 'ok']))->middleware('web');
    }

    public function test_it_adds_x_frame_options_header(): void
    {
        $response = $this->get('/test-security-headers');

        $response->assertStatus(200);
        $response->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_it_adds_x_content_type_options_header(): void
    {
        $response = $this->get('/test-security-headers');

        $response->assertStatus(200);
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_it_adds_referrer_policy_header(): void
    {
        $response = $this->get('/test-security-headers');

        $response->assertStatus(200);
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_it_adds_permissions_policy_header(): void
    {
        $response = $this->get('/test-security-headers');

        $response->assertStatus(200);
        $policy = $response->headers->get('Permissions-Policy');
        $this->assertNotNull($policy);
        $this->assertStringContainsString('camera=()', $policy);
        $this->assertStringContainsString('microphone=()', $policy);
        $this->assertStringContainsString('geolocation=(self)', $policy);
    }

    public function test_it_adds_content_security_policy_header(): void
    {
        $response = $this->get('/test-security-headers');

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
    }

    public function test_csp_contains_default_src_self(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function test_csp_contains_frame_ancestors_none(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_csp_contains_base_uri_self(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("base-uri 'self'", $csp);
    }

    public function test_csp_contains_form_action_self(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    public function test_csp_allows_cloudinary_images(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString('https://res.cloudinary.com', $csp);
    }

    public function test_csp_allows_openstreetmap_tile_images(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString('https://*.tile.openstreetmap.org', $csp);
    }

    public function test_csp_script_src_allows_unsafe_inline_for_livewire(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $csp);
    }

    public function test_csp_style_src_allows_unsafe_inline_for_tailwind(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline'", $csp);
    }

    public function test_csp_fonts_come_from_the_site_only(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        // The site serves its own fonts (core#557): no font service may be named, for styles or files
        $this->assertStringContainsString("font-src 'self' data:;", $csp);
        $this->assertStringNotContainsString('fonts.bunny.net', $csp);
        $this->assertStringNotContainsString('jsdelivr', $csp);
    }

    public function test_csp_frame_src_is_self_when_no_module_adds_origins(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-src 'self';", $csp);
    }

    public function test_csp_frame_src_lists_the_origins_modules_add(): void
    {
        $sources = $this->app->make(ContentSecurityPolicySourcesInterface::class);
        $sources->allowFrames(fn (): array => ['https://player.example.com']);
        $sources->allowFrames(fn (): array => ['https://embed.example.org', 'https://player.example.com']);

        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-src 'self' https://embed.example.org https://player.example.com;", $csp);
    }

    public function test_csp_frame_src_never_takes_a_wildcard_or_another_directive(): void
    {
        $sources = $this->app->make(ContentSecurityPolicySourcesInterface::class);
        $sources->allowFrames(fn (): array => [
            '*',
            'https://*.example.com',
            'https:',
            'http://player.example.com',
            "https://player.example.com; script-src 'unsafe-inline'",
        ]);

        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-src 'self';", $csp);
        $this->assertSame(1, substr_count($csp, 'script-src'));
    }

    public function test_csp_frame_src_stays_self_when_a_module_fails(): void
    {
        $sources = $this->app->make(ContentSecurityPolicySourcesInterface::class);
        $sources->allowFrames(fn (): array => throw new \RuntimeException('settings unreadable'));

        $response = $this->get('/test-security-headers');

        $response->assertStatus(200);
        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertNotNull($csp);
        $this->assertStringContainsString("frame-src 'self';", $csp);
    }

    public function test_csp_connect_src_has_no_websocket_origin_without_realtime(): void
    {
        config(['broadcasting.default' => 'log']);

        $csp = (string) $this->get('/test-security-headers')->headers->get('Content-Security-Policy');

        $this->assertStringNotContainsString('wss:', $csp);
        $this->assertMatchesRegularExpression("/connect-src 'self'(;| http:\\/\\/localhost)/", $csp);
    }

    public function test_csp_connect_src_allows_the_realtime_origin_when_it_is_on(): void
    {
        $this->enableRealtime(['broadcasting.connections.reverb.public.host' => 'ws.example.test']);

        $csp = (string) $this->get('/test-security-headers')->headers->get('Content-Security-Policy');

        $this->assertMatchesRegularExpression("/connect-src 'self'[^;]* wss:\\/\\/ws\\.example\\.test(;|$)/", $csp);
    }

    public function test_it_does_not_add_hsts_header_for_non_secure_requests(): void
    {
        $response = $this->get('/test-security-headers');

        $response->assertStatus(200);
        $this->assertFalse($response->headers->has('Strict-Transport-Security'));
    }

    public function test_it_adds_hsts_header_for_secure_requests(): void
    {
        // Simulate HTTPS request
        $response = $this->get('https://localhost/test-security-headers');

        $response->assertStatus(200);
        $response->assertHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
    }

    public function test_all_security_headers_are_present(): void
    {
        $response = $this->get('/test-security-headers');

        $response->assertStatus(200);
        $this->assertTrue($response->headers->has('X-Frame-Options'));
        $this->assertTrue($response->headers->has('X-Content-Type-Options'));
        $this->assertTrue($response->headers->has('Referrer-Policy'));
        $this->assertTrue($response->headers->has('Permissions-Policy'));
        $this->assertTrue($response->headers->has('Content-Security-Policy'));
    }
}
