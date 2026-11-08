<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\Social\SocialNetwork;
use App\Application\Social\SocialUrl;
use Illuminate\Support\Facades\Storage;

final readonly class SiteBrandingService implements SiteBrandingServiceInterface
{
    public function __construct(
        private SettingsServiceInterface $settings,
        private SiteNameResolver $siteName,
    ) {}

    public function siteName(): string
    {
        return $this->siteName->resolve();
    }

    public function customLogoPath(): ?string
    {
        return $this->path('site_logo_light') ?? $this->path('site_logo_dark');
    }

    public function customLogoUrl(): ?string
    {
        $path = $this->customLogoPath();

        return $path !== null ? Storage::disk('images')->url($path) : null;
    }

    public function customLogoIsDark(): bool
    {
        return $this->path('site_logo_light') === null && $this->path('site_logo_dark') !== null;
    }

    public function customFavicons(): array
    {
        return [
            'light' => $this->url('site_favicon_light'),
            'dark' => $this->url('site_favicon_dark'),
        ];
    }

    public function socialProfileUrls(): array
    {
        $urls = [];

        foreach (SocialNetwork::cases() as $network) {
            $url = SocialUrl::normalize($this->settings->get($network->settingKey(), ''), $network);

            if ($url !== '') {
                $urls[] = $url;
            }
        }

        return $urls;
    }

    private function path(string $key): ?string
    {
        $path = (string) $this->settings->get($key, '');

        return $path !== '' ? $path : null;
    }

    private function url(string $key): ?string
    {
        $path = $this->path($key);

        return $path !== null ? Storage::disk('images')->url($path) : null;
    }
}
