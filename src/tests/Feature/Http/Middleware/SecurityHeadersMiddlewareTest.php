<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Middleware;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class SecurityHeadersMiddlewareTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register a test route with web middleware (which includes security headers)
        Route::get('/test-security-headers', function () {
            return response()->json(['message' => 'ok']);
        })->middleware('web');
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
        $this->assertTrue($response->headers->has('Permissions-Policy'));
        $this->assertStringContainsString('camera=()', $response->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('microphone=()', $response->headers->get('Permissions-Policy'));
        $this->assertStringContainsString('geolocation=(self)', $response->headers->get('Permissions-Policy'));
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
        $this->assertStringContainsString("default-src 'self'", $csp);
    }

    public function test_csp_contains_frame_ancestors_none(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'none'", $csp);
    }

    public function test_csp_contains_base_uri_self(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("base-uri 'self'", $csp);
    }

    public function test_csp_contains_form_action_self(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("form-action 'self'", $csp);
    }

    public function test_csp_allows_cloudinary_images(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://res.cloudinary.com', $csp);
    }

    public function test_csp_allows_openstreetmap_tile_images(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https://*.tile.openstreetmap.org', $csp);
    }

    public function test_csp_script_src_allows_unsafe_inline_for_livewire(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'unsafe-inline' 'unsafe-eval'", $csp);
    }

    public function test_csp_style_src_allows_unsafe_inline_for_tailwind(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("style-src 'self' 'unsafe-inline' https://fonts.bunny.net", $csp);
    }

    public function test_csp_font_src_allows_bunny_fonts(): void
    {
        $response = $this->get('/test-security-headers');

        $csp = $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("font-src 'self' https://fonts.bunny.net data:", $csp);
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
