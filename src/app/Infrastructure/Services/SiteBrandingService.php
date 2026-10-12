<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\Services\AboutPageServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use Illuminate\Support\Facades\Storage;

final readonly class SiteBrandingService implements SiteBrandingServiceInterface
{
    private const array SOCIAL_NETWORKS = ['facebook', 'instagram', 'twitter', 'discord', 'tiktok', 'bluesky', 'telegram'];

    public function __construct(
        private SettingsServiceInterface $settings,
        private AboutPageServiceInterface $aboutPage,
    ) {}

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

        foreach (self::SOCIAL_NETWORKS as $network) {
            $url = $this->aboutPage->formatSocialUrl($this->settings->get("social_{$network}", ''));

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
