<?php

declare(strict_types=1);

namespace Tests\Unit\View\Components;

use App\Application\Services\SiteBrandingServiceInterface;
use App\View\Components\SiteFavicons;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class SiteFaviconsTest extends TestCase
{
    private function render(): string
    {
        return app(SiteFavicons::class)->render()->render();
    }

    private function favicons(?string $light): void
    {
        $branding = Mockery::mock(SiteBrandingServiceInterface::class);
        $branding->shouldReceive('customFavicons')->andReturn(['light' => $light, 'dark' => null]);
        $this->app->instance(SiteBrandingServiceInterface::class, $branding);
    }

    public function test_custom_favicon_replaces_the_guildforge_icons(): void
    {
        $this->favicons('https://res.cloudinary.com/demo/fav.png');

        $html = $this->render();

        $this->assertStringContainsString('<link rel="icon" type="image/png" sizes="32x32" href="https://res.cloudinary.com/demo/fav.png">', $html);
        $this->assertStringContainsString('<link rel="apple-touch-icon" sizes="180x180" href="https://res.cloudinary.com/demo/fav.png">', $html);
        $this->assertStringNotContainsString('/favicons/light/favicon', $html);
    }

    public function test_defaults_when_the_site_has_no_favicon(): void
    {
        $this->favicons(null);

        $this->assertStringContainsString('href="/favicons/light/favicon-32x32.png"', $this->render());
    }

    public function test_defaults_when_settings_fail(): void
    {
        $branding = Mockery::mock(SiteBrandingServiceInterface::class);
        $branding->shouldReceive('customFavicons')->andThrow(new RuntimeException('db down'));
        $this->app->instance(SiteBrandingServiceInterface::class, $branding);

        $this->assertStringContainsString('href="/favicons/light/favicon.ico"', $this->render());
    }
}
