<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services;

use App\Application\Services\AboutPageServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Services\SiteBrandingService;
use App\Infrastructure\Services\SiteNameResolver;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

final class SiteBrandingServiceTest extends TestCase
{
    /**
     * @param  array<string, string>  $settings
     */
    private function service(array $settings): SiteBrandingService
    {
        $settingsService = Mockery::mock(SettingsServiceInterface::class);
        $settingsService->shouldReceive('get')->andReturnUsing(
            fn (string $key, mixed $default = null): mixed => $settings[$key] ?? $default,
        );

        return new SiteBrandingService(
            $settingsService,
            app(AboutPageServiceInterface::class),
            new SiteNameResolver($settingsService),
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('images');
    }

    public function test_without_custom_logo_returns_null(): void
    {
        $service = $this->service([]);

        $this->assertNull($service->customLogoPath());
        $this->assertNull($service->customLogoUrl());
    }

    public function test_custom_logo_path_and_url(): void
    {
        $service = $this->service(['site_logo_light' => 'settings/logo.png']);

        $this->assertSame('settings/logo.png', $service->customLogoPath());
        $this->assertSame(Storage::disk('images')->url('settings/logo.png'), $service->customLogoUrl());
    }

    public function test_favicons_are_resolved_only_when_configured(): void
    {
        $service = $this->service(['site_favicon_light' => 'settings/fav.png']);

        $this->assertSame([
            'light' => Storage::disk('images')->url('settings/fav.png'),
            'dark' => null,
        ], $service->customFavicons());
    }

    public function test_light_logo_wins_over_the_dark_one(): void
    {
        $service = $this->service(['site_logo_light' => 'settings/light.png', 'site_logo_dark' => 'settings/dark.png']);

        $this->assertSame('settings/light.png', $service->customLogoPath());
        $this->assertFalse($service->customLogoIsDark());
    }

    public function test_dark_logo_is_the_site_logo_when_there_is_no_light_one(): void
    {
        $service = $this->service(['site_logo_dark' => 'settings/dark.png']);

        $this->assertSame('settings/dark.png', $service->customLogoPath());
        $this->assertSame(Storage::disk('images')->url('settings/dark.png'), $service->customLogoUrl());
        $this->assertTrue($service->customLogoIsDark());
    }

    public function test_social_profiles_are_absolute_and_skip_empty_values(): void
    {
        $service = $this->service([
            'social_instagram' => 'instagram.com/asociacion-ejemplo',
            'social_facebook' => 'https://facebook.com/asociacion-ejemplo',
            'social_twitter' => '',
        ]);

        $this->assertSame(
            ['https://facebook.com/asociacion-ejemplo', 'https://instagram.com/asociacion-ejemplo'],
            $service->socialProfileUrls(),
        );
    }

    public function test_site_name_is_the_association_name_from_the_settings(): void
    {
        config(['app.name' => 'Instalación genérica']);

        $this->assertSame('Asociación de ejemplo', $this->service(['guild_name' => '  Asociación de ejemplo '])->siteName());
    }

    public function test_site_name_falls_back_to_the_installation_name(): void
    {
        config(['app.name' => 'Instalación genérica']);

        $this->assertSame('Instalación genérica', $this->service([])->siteName());
        $this->assertSame('Instalación genérica', $this->service(['guild_name' => '   '])->siteName());
    }
}
