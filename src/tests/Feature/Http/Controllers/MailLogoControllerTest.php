<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Mail\Services\MailLogoServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class MailLogoControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string SVG = '<svg xmlns="http://www.w3.org/2000/svg" width="400" height="100" viewBox="0 0 400 100"><rect width="400" height="100" fill="#d97706"/></svg>';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('images');
    }

    private function settings(): SettingsServiceInterface
    {
        return app(SettingsServiceInterface::class);
    }

    private function useSvgLogo(): void
    {
        Storage::disk('images')->put('branding/logo-email.svg', self::SVG);
        $this->settings()->set('site_logo_email', 'branding/logo-email.svg');
    }

    public function test_it_serves_the_svg_logo_as_a_png_for_everyone_to_cache(): void
    {
        $this->useSvgLogo();

        $response = $this->get('/correos/logo.png');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'image/png');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringStartsWith("\x89PNG", (string) $response->getContent());
        $this->assertStringContainsString('public', (string) $response->headers->get('Cache-Control'));
        $this->assertNull($response->headers->get('Set-Cookie'));
    }

    public function test_the_current_version_is_cached_for_long(): void
    {
        $this->useSvgLogo();
        $png = app(MailLogoServiceInterface::class)->png();
        $this->assertNotNull($png);

        $response = $this->get('/correos/logo.png?v='.$png->version);

        $response->assertOk();
        $this->assertStringContainsString('immutable', (string) $response->headers->get('Cache-Control'));
    }

    public function test_an_old_version_gets_the_current_logo_for_a_short_while(): void
    {
        $this->useSvgLogo();

        $response = $this->get('/correos/logo.png?v=0000000000000000');

        $response->assertOk();
        $this->assertStringNotContainsString('immutable', (string) $response->headers->get('Cache-Control'));
    }

    public function test_it_answers_not_found_when_the_logo_is_not_an_svg(): void
    {
        $this->settings()->set('site_logo_email', 'branding/logo-email.png');

        $this->get('/correos/logo.png')->assertNotFound();
    }

    public function test_it_answers_not_found_without_a_logo(): void
    {
        $this->get('/correos/logo.png')->assertNotFound();
    }

    public function test_it_keeps_serving_the_logo_in_maintenance_mode(): void
    {
        $this->useSvgLogo();
        $this->settings()->set('maintenance_enabled', '1');

        $this->get('/correos/logo.png')->assertOk();
    }
}
