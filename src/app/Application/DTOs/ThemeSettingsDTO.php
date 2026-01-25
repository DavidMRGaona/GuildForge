<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Application\Services\SettingsServiceInterface;

final readonly class ThemeSettingsDTO
{
    public function __construct(
        public string $primaryColor = '#D97706',           // amber-600 (richer for light mode)
        public string $primaryColorDark = '#F59E0B',       // amber-500 (glowing for dark mode)
        public string $secondaryColor = '#57534E',         // stone-600
        public string $secondaryColorDark = '#A8A29E',     // stone-400 (lighter in dark)
        public string $accentColor = '#D97706',            // amber-600
        public string $backgroundColor = '#FAFAF9',        // stone-50 (warm white)
        public string $backgroundColorDark = '#1C1917',    // stone-900 (NOT pure black)
        public string $surfaceColor = '#FFFFFF',           // white for cards
        public string $surfaceColorDark = '#292524',       // stone-800
        public string $textColor = '#1C1917',              // stone-900
        public string $textColorDark = '#F5F5F4',          // stone-100 (soft white)
        public string $textSecondaryColor = '#57534E',     // stone-600
        public string $textSecondaryColorDark = '#D6D3D1', // stone-300
        public string $textMutedColor = '#A8A29E',         // stone-400
        public string $textMutedColorDark = '#A8A29E',     // stone-400
        public string $borderColor = '#E7E5E4',            // stone-200
        public string $borderColorDark = '#44403C',        // stone-700
        public string $fontHeading = 'Inter',
        public string $fontBody = 'Inter',
        public string $fontSizeBase = '16px',
        public string $borderRadius = '0.5rem',
        public string $shadowIntensity = '0.5',
        public string $buttonStyle = 'solid',
        public bool $darkModeDefault = false,
        public bool $darkModeToggleVisible = true,
    ) {
    }

    public static function defaults(): self
    {
        return new self();
    }

    public static function fromSettings(SettingsServiceInterface $settings): self
    {
        return new self(
            primaryColor: self::getColorOrDefault($settings, 'theme_primary_color', '#D97706'),
            primaryColorDark: self::getColorOrDefault($settings, 'theme_primary_color_dark', '#F59E0B'),
            secondaryColor: self::getColorOrDefault($settings, 'theme_secondary_color', '#57534E'),
            secondaryColorDark: self::getColorOrDefault($settings, 'theme_secondary_color_dark', '#A8A29E'),
            accentColor: self::getColorOrDefault($settings, 'theme_accent_color', '#D97706'),
            backgroundColor: self::getColorOrDefault($settings, 'theme_background_color', '#FAFAF9'),
            backgroundColorDark: self::getColorOrDefault($settings, 'theme_background_color_dark', '#1C1917'),
            surfaceColor: self::getColorOrDefault($settings, 'theme_surface_color', '#FFFFFF'),
            surfaceColorDark: self::getColorOrDefault($settings, 'theme_surface_color_dark', '#292524'),
            textColor: self::getColorOrDefault($settings, 'theme_text_color', '#1C1917'),
            textColorDark: self::getColorOrDefault($settings, 'theme_text_color_dark', '#F5F5F4'),
            textSecondaryColor: self::getColorOrDefault($settings, 'theme_text_secondary_color', '#57534E'),
            textSecondaryColorDark: self::getColorOrDefault($settings, 'theme_text_secondary_color_dark', '#D6D3D1'),
            textMutedColor: self::getColorOrDefault($settings, 'theme_text_muted_color', '#A8A29E'),
            textMutedColorDark: self::getColorOrDefault($settings, 'theme_text_muted_color_dark', '#A8A29E'),
            borderColor: self::getColorOrDefault($settings, 'theme_border_color', '#E7E5E4'),
            borderColorDark: self::getColorOrDefault($settings, 'theme_border_color_dark', '#44403C'),
            fontHeading: self::getStringOrDefault($settings, 'theme_font_heading', 'Inter'),
            fontBody: self::getStringOrDefault($settings, 'theme_font_body', 'Inter'),
            fontSizeBase: self::mapFontSize((string) $settings->get('theme_font_size_base', 'normal')),
            borderRadius: self::mapBorderRadius((string) $settings->get('theme_border_radius', 'medium')),
            shadowIntensity: self::mapShadowIntensity((string) $settings->get('theme_shadow_intensity', 'subtle')),
            buttonStyle: self::getStringOrDefault($settings, 'theme_button_style', 'solid'),
            darkModeDefault: self::toBool($settings->get('theme_dark_mode_default', false)),
            darkModeToggleVisible: self::toBool($settings->get('theme_dark_mode_toggle_visible', true)),
        );
    }

    /**
     * Get a color value, returning default if empty.
     */
    private static function getColorOrDefault(SettingsServiceInterface $settings, string $key, string $default): string
    {
        $value = (string) $settings->get($key, '');

        return $value !== '' ? $value : $default;
    }

    /**
     * Get a string value, returning default if empty.
     */
    private static function getStringOrDefault(SettingsServiceInterface $settings, string $key, string $default): string
    {
        $value = (string) $settings->get($key, '');

        return $value !== '' ? $value : $default;
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
