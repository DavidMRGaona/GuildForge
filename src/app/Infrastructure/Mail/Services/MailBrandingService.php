<?php

declare(strict_types=1);

namespace App\Infrastructure\Mail\Services;

use App\Application\Mail\DTOs\Response\MailBrandingDTO;
use App\Application\Mail\Services\MailBrandingServiceInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use App\Domain\ValueObjects\ColorPalette;
use App\Domain\ValueObjects\HexColor;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Builds the email theme from the site settings.
 *
 * Bound scoped: computed once per request or queue job and shared by every
 * mail view rendered in it.
 */
final class MailBrandingService implements MailBrandingServiceInterface
{
    private const string WHITE = '#FFFFFF';

    private const string SYSTEM_FONTS = '-apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif';

    private const string FONT_NAME_PATTERN = '/^[A-Za-z0-9 ]{1,40}$/';

    private const int MAX_RADIUS_PX = 16;

    private const float MIN_BUTTON_CONTRAST = 3.0;

    private ?MailBrandingDTO $branding = null;

    public function __construct(
        private readonly SettingsServiceInterface $settings,
        private readonly ThemeSettingsServiceInterface $theme,
        private readonly SiteBrandingServiceInterface $site,
    ) {}

    public function branding(): MailBrandingDTO
    {
        if ($this->branding !== null) {
            return $this->branding;
        }

        try {
            $this->branding = $this->fromSettings();
        } catch (Throwable $e) {
            Log::warning('[MailBrandingService] Falling back to the default email theme: '.$e->getMessage());
            $this->branding = $this->defaults();
        }

        return $this->branding;
    }

    private function fromSettings(): MailBrandingDTO
    {
        $primary = $this->theme->getPrimaryPalette();
        $neutral = $this->theme->getNeutralPalette();
        $theme = $this->theme->getThemeSettings();
        $white = new HexColor(self::WHITE);

        // Buttons keep the chosen color (shade 600, as on the site); white text
        // needs the 3:1 contrast of bold text, otherwise the darkest neutral.
        $button = $primary->shade(600) ?? $primary->baseColor();
        $onButton = $button->contrastRatio($white) >= self::MIN_BUTTON_CONTRAST ? self::WHITE : $this->shade($neutral, 950);

        $headingFont = $this->fontName($theme->fontHeading);
        $bodyFont = $this->fontName($theme->fontBody);

        return new MailBrandingDTO(
            siteName: $this->site->siteName(),
            siteUrl: $this->siteUrl(),
            logoUrl: $this->settings->getEmailLogoUrl(),
            primaryColor: $button->value,
            onPrimaryColor: $onButton,
            linkColor: $this->firstReadableShade($primary, [700, 800, 900, 950], $white) ?? $this->shade($neutral, 900),
            panelColor: $this->shade($primary, 50),
            panelBorderColor: $this->shade($primary, 500),
            backgroundColor: $this->shade($neutral, 100),
            surfaceColor: self::WHITE,
            headingColor: $this->shade($neutral, 900),
            textColor: $this->shade($neutral, 700),
            mutedColor: $this->shade($neutral, 500),
            borderColor: $this->shade($neutral, 200),
            fontHeading: $this->fontStack($headingFont),
            fontBody: $this->fontStack($bodyFont),
            borderRadius: $this->radiusInPixels($theme->borderRadius),
            fontStylesheetUrl: $this->fontStylesheetUrl([$headingFont, $bodyFont]),
        );
    }

    private function defaults(): MailBrandingDTO
    {
        return new MailBrandingDTO(
            siteName: $this->installationName(),
            siteUrl: $this->siteUrl(),
            logoUrl: null,
            primaryColor: '#D97706',
            onPrimaryColor: self::WHITE,
            linkColor: '#92400E',
            panelColor: '#FFFBEB',
            panelBorderColor: '#F59E0B',
            backgroundColor: '#F4F4F5',
            surfaceColor: self::WHITE,
            headingColor: '#18181B',
            textColor: '#3F3F46',
            mutedColor: '#71717A',
            borderColor: '#E4E4E7',
            fontHeading: self::SYSTEM_FONTS,
            fontBody: self::SYSTEM_FONTS,
            borderRadius: '8px',
            fontStylesheetUrl: null,
        );
    }

    /** The settings may be what failed: the installation name needs none. */
    private function installationName(): string
    {
        return (string) config('app.name', 'GuildForge');
    }

    private function siteUrl(): string
    {
        return (string) config('app.url');
    }

    /**
     * @param  list<int>  $keys
     */
    private function firstReadableShade(ColorPalette $palette, array $keys, HexColor $against): ?string
    {
        foreach ($keys as $key) {
            $shade = $palette->shade($key);

            if ($shade !== null && $shade->meetsWcagAA($against)) {
                return $shade->value;
            }
        }

        return null;
    }

    private function shade(ColorPalette $palette, int $key): string
    {
        return ($palette->shade($key) ?? $palette->baseColor())->value;
    }

    /**
     * The configured font when it is a plain family name, null for the system font.
     */
    private function fontName(string $font): ?string
    {
        if ($font === 'system-ui' || preg_match(self::FONT_NAME_PATTERN, $font) !== 1) {
            return null;
        }

        return $font;
    }

    private function fontStack(?string $font): string
    {
        return $font === null ? self::SYSTEM_FONTS : "'{$font}', ".self::SYSTEM_FONTS;
    }

    /**
     * @param  list<string|null>  $fonts
     */
    private function fontStylesheetUrl(array $fonts): ?string
    {
        $families = array_values(array_unique(array_map(
            static fn (string $font): string => str_replace(' ', '-', strtolower($font)).':400,600,700',
            array_filter($fonts, static fn (?string $font): bool => $font !== null),
        )));

        return $families === [] ? null : 'https://fonts.bunny.net/css?family='.implode('|', $families);
    }

    private function radiusInPixels(string $radius): string
    {
        $pixels = str_ends_with($radius, 'rem')
            ? (float) $radius * 16
            : (float) $radius;

        $pixels = (int) round(min(max($pixels, 0), self::MAX_RADIUS_PX));

        return $pixels === 0 ? '0' : $pixels.'px';
    }
}
