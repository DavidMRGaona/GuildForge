<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Application\Services\SettingsServiceInterface;

final readonly class ThemeSettingsDTO
{
    /** The generic system font: written to the CSS without quotes */
    public const string SYSTEM_FONT = 'system-ui';

    /** Families offered for body text, served by Bunny Fonts (all but the system font) */
    public const array BODY_FONTS = ['Inter', 'Poppins', 'Montserrat', 'Roboto', 'Open Sans', self::SYSTEM_FONT];

    /** Families offered for headings: the body ones plus a serif display face */
    public const array HEADING_FONTS = ['Inter', 'Poppins', 'Montserrat', 'Roboto', 'Open Sans', 'Playfair Display', self::SYSTEM_FONT];

    /** Families that fall back to a serif stack instead of a sans-serif one */
    public const array SERIF_FONTS = ['Playfair Display'];

    /** Looks of the primary button */
    public const array BUTTON_STYLES = ['solid', 'outline', 'ghost'];

    private const string DEFAULT_FONT = 'Inter';

    private const string DEFAULT_BUTTON_STYLE = 'solid';

    public function __construct(
        public string $fontHeading = self::DEFAULT_FONT,
        public string $fontBody = self::DEFAULT_FONT,
        public string $fontSizeBase = '16px',
        public string $borderRadius = '0.5rem',
        public string $shadowIntensity = '1',
        public string $buttonStyle = 'solid',
        public bool $darkModeDefault = false,
        public bool $darkModeToggleVisible = true,
    ) {}

    public static function defaults(): self
    {
        return new self;
    }

    public static function fromSettings(SettingsServiceInterface $settings): self
    {
        return new self(
            fontHeading: self::oneOf($settings, 'theme_font_heading', self::HEADING_FONTS, self::DEFAULT_FONT),
            fontBody: self::oneOf($settings, 'theme_font_body', self::BODY_FONTS, self::DEFAULT_FONT),
            fontSizeBase: self::mapFontSize((string) $settings->get('theme_font_size_base', 'normal')),
            borderRadius: self::mapBorderRadius((string) $settings->get('theme_border_radius', 'medium')),
            shadowIntensity: self::mapShadowIntensity((string) $settings->get('theme_shadow_intensity', 'medium')),
            buttonStyle: self::oneOf($settings, 'theme_button_style', self::BUTTON_STYLES, self::DEFAULT_BUTTON_STYLE),
            darkModeDefault: self::toBool($settings->get('theme_dark_mode_default', false)),
            darkModeToggleVisible: self::toBool($settings->get('theme_dark_mode_toggle_visible', true)),
        );
    }

    /**
     * The stored value when it is one of the options, the default otherwise. These values end up
     * inside the site stylesheet, so nothing outside the list may get through.
     *
     * @param  list<string>  $options
     */
    private static function oneOf(SettingsServiceInterface $settings, string $key, array $options, string $default): string
    {
        $value = $settings->get($key, '');

        return is_string($value) && in_array($value, $options, true) ? $value : $default;
    }

    /**
     * Map font size descriptor to CSS value.
     */
    private static function mapFontSize(string $descriptor): string
    {
        return match ($descriptor) {
            'small' => '14px',
            'large' => '18px',
            default => '16px', // 'normal' or any other value
        };
    }

    /**
     * Map border radius descriptor to CSS value.
     */
    private static function mapBorderRadius(string $descriptor): string
    {
        return match ($descriptor) {
            'none' => '0',
            'subtle' => '0.25rem',
            'large' => '0.75rem',
            'rounded' => '1rem',
            default => '0.5rem', // 'medium' or any other value
        };
    }

    /**
     * Map shadow intensity descriptor to CSS multiplier.
     */
    private static function mapShadowIntensity(string $descriptor): string
    {
        return match ($descriptor) {
            'none' => '0',
            'subtle' => '0.5',
            'pronounced' => '1.5',
            default => '1', // 'medium' or any other value
        };
    }

    private static function toBool(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if (is_string($value)) {
            return $value === '1' || strtolower($value) === 'true';
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        return false;
    }
}
