<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\WebApp;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use App\Application\WebApp\Services\WebAppManifestInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Mockery;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class WebAppManifestControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['cloudinary.cloud_name' => 'demo', 'cloudinary.prefix' => 'site']);
    }

    public function test_it_answers_a_web_app_manifest_named_after_the_site(): void
    {
        app(SettingsServiceInterface::class)->set('guild_name', 'Asociación de ejemplo');

        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $this->assertStringStartsWith('application/manifest+json', (string) $response->headers->get('Content-Type'));
        $response->assertJson([
            'name' => 'Asociación de ejemplo',
            'short_name' => 'Asociación de ejemplo',
            'id' => '/',
            'start_url' => '/',
            'scope' => '/',
            'display' => 'standalone',
            'lang' => 'es',
        ]);
    }

    public function test_it_takes_its_colours_from_the_theme(): void
    {
        $theme = app(ThemeSettingsServiceInterface::class);
        $expectedTheme = (string) $theme->getPrimaryPalette()->shade(600);
        $expectedBackground = (string) $theme->getNeutralPalette()->shade(50);

        $this->get('/manifest.webmanifest')->assertJson([
            'theme_color' => $expectedTheme,
            'background_color' => $expectedBackground,
        ]);
    }

    public function test_without_a_favicon_it_offers_the_core_icons(): void
    {
        $icons = $this->icons($this->get('/manifest.webmanifest'));

        $this->assertSame([
            ['src' => '/favicons/light/android-chrome-192x192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/favicons/light/android-chrome-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => '/favicons/light/maskable-192x192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'maskable'],
            ['src' => '/favicons/light/maskable-512x512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ], $icons);
    }

    public function test_the_configured_favicon_becomes_the_app_icons(): void
    {
        app(SettingsServiceInterface::class)->set('site_favicon_light', 'branding/favicon-light-abc.png');

        $icons = $this->icons($this->get('/manifest.webmanifest'));

        $this->assertCount(4, $icons);
        $this->assertSame('https://res.cloudinary.com/demo/image/upload/w_192,h_192,c_pad,f_png/site/branding/favicon-light-abc', $icons[0]['src']);
        $this->assertSame(['192x192', 'image/png', 'any'], [$icons[0]['sizes'], $icons[0]['type'], $icons[0]['purpose']]);
        $this->assertSame('512x512', $icons[1]['sizes']);
        $this->assertSame('maskable', $icons[2]['purpose']);
        $this->assertSame('image/jpeg', $icons[2]['type']);
        $this->assertStringContainsString('/site/branding/favicon-light-abc', $icons[3]['src']);
    }

    public function test_the_dark_favicon_is_used_when_there_is_no_light_one(): void
    {
        app(SettingsServiceInterface::class)->set('site_favicon_dark', 'branding/favicon-dark-abc.ico');

        $icons = $this->icons($this->get('/manifest.webmanifest'));

        $this->assertStringEndsWith('/site/branding/favicon-dark-abc', $icons[0]['src']);
    }

    public function test_it_never_carries_anything_about_the_account(): void
    {
        $user = UserModel::factory()->create(['name' => 'cuenta-secreta', 'email' => 'secreta@example.com']);

        $body = (string) $this->actingAs($user)->get('/manifest.webmanifest')->assertOk()->getContent();

        $this->assertStringNotContainsString('cuenta-secreta', $body);
        $this->assertStringNotContainsString('secreta@example.com', $body);
        $this->assertStringNotContainsString($user->id, $body);
    }

    public function test_it_sets_no_cookie_and_asks_to_be_revalidated(): void
    {
        $response = $this->get('/manifest.webmanifest');

        $this->assertSame([], $response->headers->getCookies());
        $this->assertStringContainsString('no-cache', (string) $response->headers->get('Cache-Control'));
        $this->assertNotNull($response->headers->get('ETag'));
    }

    public function test_it_falls_back_to_the_core_icons_and_colours_when_the_settings_fail(): void
    {
        $settings = Mockery::mock(SettingsServiceInterface::class);
        $settings->shouldReceive('isMaintenanceModeEnabled')->andReturnFalse();
        $settings->shouldReceive('get')->andThrow(new RuntimeException('database down'));
        $this->app->instance(SettingsServiceInterface::class, $settings);
        $theme = Mockery::mock(ThemeSettingsServiceInterface::class);
        $theme->shouldReceive('getPrimaryPalette', 'getNeutralPalette')->andThrow(new RuntimeException('database down'));
        $this->app->instance(ThemeSettingsServiceInterface::class, $theme);

        $response = $this->get('/manifest.webmanifest');

        $response->assertOk();
        $response->assertJsonPath('icons.0.src', '/favicons/light/android-chrome-192x192.png');
        $response->assertJsonPath('theme_color', '#D97706');
        $response->assertJsonPath('background_color', '#FAFAF9');
        $this->assertNotSame('', $response->json('name'));
    }

    public function test_the_home_screen_icon_is_the_favicon_padded_on_the_page_background(): void
    {
        app(SettingsServiceInterface::class)->set('site_favicon_light', 'branding/favicon-light-abc.png');
        $background = ltrim((string) app(ThemeSettingsServiceInterface::class)->getNeutralPalette()->shade(50), '#');

        $this->assertSame(
            "https://res.cloudinary.com/demo/image/upload/w_144,h_144,c_pad,b_rgb:{$background}/w_180,h_180,c_lpad,b_rgb:{$background},q_90,f_jpg/site/branding/favicon-light-abc",
            app(WebAppManifestInterface::class)->appleTouchIcon(),
        );
    }

    public function test_the_home_screen_icon_is_the_core_one_without_a_favicon_or_cloudinary(): void
    {
        $this->assertSame('/favicons/light/apple-touch-icon.png', app(WebAppManifestInterface::class)->appleTouchIcon());

        app(SettingsServiceInterface::class)->set('site_favicon_light', 'branding/favicon-light-abc.png');
        config(['cloudinary.cloud_name' => '']);

        $this->assertSame('/favicons/light/apple-touch-icon.png', app(WebAppManifestInterface::class)->appleTouchIcon());
    }

    /**
     * @param  TestResponse<Response>  $response
     * @return list<array{src: string, sizes: string, type: string, purpose: string}>
     */
    private function icons(TestResponse $response): array
    {
        $response->assertOk();

        /** @var list<array{src: string, sizes: string, type: string, purpose: string}> $icons */
        $icons = $response->json('icons');

        return $icons;
    }
}
