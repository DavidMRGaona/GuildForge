<?php

declare(strict_types=1);

namespace App\Infrastructure\WebApp;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use App\Application\WebApp\DTOs\WebAppIconDTO;
use App\Application\WebApp\DTOs\WebAppManifestDTO;
use App\Application\WebApp\Services\WebAppManifestInterface;
use App\Domain\ValueObjects\ColorPalette;
use App\Domain\ValueObjects\HexColor;
use App\Infrastructure\Seo\CloudinaryImageUrlBuilder;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Name, colours and icons of the installable site (core#802). The icons come from the configured
 * favicon (the light one, else the dark one) through Cloudinary; without one, or without Cloudinary,
 * the core's own. Each part falls back on its own, so a failing setting never breaks the install.
 */
final readonly class WebAppManifest implements WebAppManifestInterface
{
    /** The core's colours, as the theme's defaults draw them (primary 600 and neutral 50). */
    public const string DEFAULT_THEME_COLOR = '#D97706';

    public const string DEFAULT_BACKGROUND_COLOR = '#FAFAF9';

    private const array SIZES = [192, 512];

    /** A maskable icon may be cropped to a circle of 80 % of its side: a square fits in 56 % of it. */
    private const float MASKABLE_ARTWORK = 0.5625;

    private const int APPLE_TOUCH_SIZE = 180;

    private const int APPLE_TOUCH_ARTWORK = 144;

    private const string CORE_ICONS = '/favicons/light';

    public function __construct(
        private SiteBrandingServiceInterface $branding,
        private SettingsServiceInterface $settings,
        private ThemeSettingsServiceInterface $theme,
        private CloudinaryImageUrlBuilder $cloudinary,
    ) {}

    public function manifest(): WebAppManifestDTO
    {
        return new WebAppManifestDTO(
            name: $this->name(),
            lang: str_replace('_', '-', app()->getLocale()),
            themeColor: $this->colour(fn (): ColorPalette => $this->theme->getPrimaryPalette(), 600, self::DEFAULT_THEME_COLOR),
            backgroundColor: $this->backgroundColour(),
            icons: $this->icons(),
        );
    }

    public function appleTouchIcon(): string
    {
        $favicon = $this->favicon();
        $icon = $favicon !== null
            ? $this->cloudinary->paddedAppIcon($favicon, self::APPLE_TOUCH_SIZE, self::APPLE_TOUCH_ARTWORK, $this->backgroundColour())
            : null;

        return $icon ?? self::CORE_ICONS.'/apple-touch-icon.png';
    }

    public function notificationIcon(): string
    {
        foreach ($this->icons() as $icon) {
            if ($icon->purpose === WebAppIconDTO::PURPOSE_ANY) {
                return $icon->src;
            }
        }

        return self::CORE_ICONS.'/android-chrome-192x192.png';
    }

    /**
     * @return list<WebAppIconDTO>
     */
    private function icons(): array
    {
        $favicon = $this->favicon();

        if ($favicon !== null) {
            $icons = $this->iconsFrom($favicon);

            if ($icons !== null) {
                return $icons;
            }
        }

        $icons = [];
        foreach (self::SIZES as $size) {
            $icons[] = new WebAppIconDTO(self::CORE_ICONS."/android-chrome-{$size}x{$size}.png", $size, 'image/png', WebAppIconDTO::PURPOSE_ANY);
        }
        foreach (self::SIZES as $size) {
            $icons[] = new WebAppIconDTO(self::CORE_ICONS."/maskable-{$size}x{$size}.png", $size, 'image/png', WebAppIconDTO::PURPOSE_MASKABLE);
        }

        return $icons;
    }

    /**
     * @return list<WebAppIconDTO>|null null when Cloudinary cannot build them
     */
    private function iconsFrom(string $favicon): ?array
    {
        $background = $this->backgroundColour();
        $any = [];
        $maskable = [];

        foreach (self::SIZES as $size) {
            $plain = $this->cloudinary->appIcon($favicon, $size);
            $padded = $this->cloudinary->paddedAppIcon($favicon, $size, (int) round($size * self::MASKABLE_ARTWORK), $background);

            if ($plain === null || $padded === null) {
                return null;
            }

            $any[] = new WebAppIconDTO($plain, $size, 'image/png', WebAppIconDTO::PURPOSE_ANY);
            $maskable[] = new WebAppIconDTO($padded, $size, 'image/jpeg', WebAppIconDTO::PURPOSE_MASKABLE);
        }

        return [...$any, ...$maskable];
    }

    private function name(): string
    {
        try {
            $name = trim($this->branding->siteName());
        } catch (Throwable $exception) {
            $this->warn('name', $exception);
            $name = '';
        }

        return $name !== '' ? $name : (string) config('app.name');
    }

    private function favicon(): ?string
    {
        try {
            foreach (['site_favicon_light', 'site_favicon_dark'] as $key) {
                $path = $this->settings->get($key, '');

                if (is_string($path) && trim($path) !== '') {
                    return trim($path);
                }
            }
        } catch (Throwable $exception) {
            $this->warn('favicon', $exception);
        }

        return null;
    }

    private function backgroundColour(): string
    {
        return $this->colour(fn (): ColorPalette => $this->theme->getNeutralPalette(), 50, self::DEFAULT_BACKGROUND_COLOR);
    }

    /**
     * @param  Closure(): ColorPalette  $palette
     */
    private function colour(Closure $palette, int $shade, string $default): string
    {
        try {
            $colour = $palette()->shade($shade);
        } catch (Throwable $exception) {
            $this->warn('colours', $exception);
            $colour = null;
        }

        return $colour instanceof HexColor ? $colour->value : $default;
    }

    private function warn(string $part, Throwable $exception): void
    {
        Log::warning('Web app manifest fell back to the core defaults', [
            'part' => $part,
            'exception' => $exception::class,
        ]);
    }
}
