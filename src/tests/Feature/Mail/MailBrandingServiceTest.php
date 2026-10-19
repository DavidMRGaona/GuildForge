<?php

declare(strict_types=1);

namespace Tests\Feature\Mail;

use App\Application\Mail\Services\MailBrandingServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use App\Domain\ValueObjects\HexColor;
use App\Infrastructure\Mail\Services\MailBrandingService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\TestCase;

final class MailBrandingServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.name' => 'Asociación Tolerol', 'app.url' => 'https://tolerol.example']);
        Storage::fake('images');
    }

    private function settings(): SettingsServiceInterface
    {
        return app(SettingsServiceInterface::class);
    }

    private function service(): MailBrandingServiceInterface
    {
        return new MailBrandingService(
            $this->settings(),
            app(ThemeSettingsServiceInterface::class),
        );
    }

    public function test_it_uses_the_site_name_url_and_email_logo(): void
    {
        $this->settings()->set('site_logo_email', 'settings/email-logo.png');

        $branding = $this->service()->branding();

        $this->assertSame('Asociación Tolerol', $branding->siteName);
        $this->assertSame('https://tolerol.example', $branding->siteUrl);
        $this->assertSame(Storage::disk('images')->url('settings/email-logo.png'), $branding->logoUrl);
    }

    public function test_button_uses_the_primary_color_when_white_text_is_readable(): void
    {
        $this->settings()->set('theme_primary_base_color', '#7C3AED');
        $palette = app(ThemeSettingsServiceInterface::class)->getPrimaryPalette();

        $branding = $this->service()->branding();

        $this->assertSame($palette->shade(600)?->value, $branding->primaryColor);
        $this->assertSame('#FFFFFF', $branding->onPrimaryColor);
    }

    public function test_button_keeps_a_light_primary_color_with_dark_text(): void
    {
        $this->settings()->set('theme_primary_base_color', '#FACC15');
        $palette = app(ThemeSettingsServiceInterface::class)->getPrimaryPalette();

        $branding = $this->service()->branding();

        $this->assertSame($palette->shade(600)?->value, $branding->primaryColor);
        $this->assertNotSame('#FFFFFF', $branding->onPrimaryColor);
        $this->assertGreaterThanOrEqual(3.0, (new HexColor($branding->primaryColor))->contrastRatio(new HexColor($branding->onPrimaryColor)));
    }

    public function test_links_are_readable_on_white(): void
    {
        $this->settings()->set('theme_primary_base_color', '#FACC15');

        $branding = $this->service()->branding();

        $this->assertTrue((new HexColor($branding->linkColor))->meetsWcagAA(new HexColor('#FFFFFF')));
    }

    public function test_it_uses_the_site_fonts_with_an_email_safe_fallback(): void
    {
        $this->settings()->set('theme_font_heading', 'Montserrat');
        $this->settings()->set('theme_font_body', 'Open Sans');

        $branding = $this->service()->branding();

        $this->assertStringStartsWith("'Montserrat', ", $branding->fontHeading);
        $this->assertStringStartsWith("'Open Sans', ", $branding->fontBody);
        $this->assertStringEndsWith('sans-serif', $branding->fontBody);
        $this->assertSame('https://fonts.bunny.net/css?family=montserrat:400,600,700|open-sans:400,600,700', $branding->fontStylesheetUrl);
    }

    public function test_system_font_needs_no_stylesheet(): void
    {
        $this->settings()->set('theme_font_heading', 'system-ui');
        $this->settings()->set('theme_font_body', 'system-ui');

        $branding = $this->service()->branding();

        $this->assertNull($branding->fontStylesheetUrl);
        $this->assertStringNotContainsString("'", $branding->fontBody);
    }

    public function test_an_unexpected_font_value_falls_back_to_the_system_font(): void
    {
        $this->settings()->set('theme_font_body', "Inter'; } body { display: none");

        $branding = $this->service()->branding();

        $this->assertStringNotContainsString('display', $branding->fontBody);
        $this->assertStringNotContainsString("'", $branding->fontBody);
    }

    public function test_border_radius_follows_the_site_setting_in_pixels(): void
    {
        $this->settings()->set('theme_border_radius', 'large');
        $this->assertSame('12px', $this->service()->branding()->borderRadius);

        $this->settings()->set('theme_border_radius', 'none');
        $this->assertSame('0', $this->service()->branding()->borderRadius);
    }

    public function test_it_falls_back_to_defaults_when_settings_fail(): void
    {
        $settings = $this->createStub(SettingsServiceInterface::class);
        $settings->method('get')->willThrowException(new RuntimeException('cache down'));
        $settings->method('getEmailLogoUrl')->willThrowException(new RuntimeException('cache down'));
        $theme = $this->createStub(ThemeSettingsServiceInterface::class);
        $theme->method('getPrimaryPalette')->willThrowException(new RuntimeException('cache down'));
        $theme->method('getNeutralPalette')->willThrowException(new RuntimeException('cache down'));
        $theme->method('getThemeSettings')->willThrowException(new RuntimeException('cache down'));

        $branding = (new MailBrandingService($settings, $theme))->branding();

        $this->assertSame('Asociación Tolerol', $branding->siteName);
        $this->assertNull($branding->logoUrl);
        $this->assertGreaterThanOrEqual(3.0, (new HexColor($branding->primaryColor))->contrastRatio(new HexColor($branding->onPrimaryColor)));
        $this->assertTrue((new HexColor($branding->linkColor))->meetsWcagAA(new HexColor('#FFFFFF')));
    }
}
