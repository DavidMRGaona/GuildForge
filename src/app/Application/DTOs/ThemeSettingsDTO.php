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
        public string $fontHeading = 'Inter',
        public string $fontBody = 'Inter',
        public string $fontSizeBase = '16px',
        public string $borderRadius = '0.5rem',
        public string $shadowIntensity = 'subtle',
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
            primaryColor: (string) $settings->get('theme_primary_color', '#D97706'),
            primaryColorDark: (string) $settings->get('theme_primary_color_dark', '#F59E0B'),
            secondaryColor: (string) $settings->get('theme_secondary_color', '#57534E'),
            secondaryColorDark: (string) $settings->get('theme_secondary_color_dark', '#A8A29E'),
            accentColor: (string) $settings->get('theme_accent_color', '#D97706'),
            backgroundColor: (string) $settings->get('theme_background_color', '#FAFAF9'),
            backgroundColorDark: (string) $settings->get('theme_background_color_dark', '#1C1917'),
            surfaceColor: (string) $settings->get('theme_surface_color', '#FFFFFF'),
            surfaceColorDark: (string) $settings->get('theme_surface_color_dark', '#292524'),
            textColor: (string) $settings->get('theme_text_color', '#1C1917'),
            textColorDark: (string) $settings->get('theme_text_color_dark', '#F5F5F4'),
            fontHeading: (string) $settings->get('theme_font_heading', 'Inter'),
            fontBody: (string) $settings->get('theme_font_body', 'Inter'),
            fontSizeBase: (string) $settings->get('theme_font_size_base', '16px'),
            borderRadius: (string) $settings->get('theme_border_radius', '0.5rem'),
            shadowIntensity: (string) $settings->get('theme_shadow_intensity', 'subtle'),
            buttonStyle: (string) $settings->get('theme_button_style', 'solid'),
            darkModeDefault: self::toBool($settings->get('theme_dark_mode_default', false)),
            darkModeToggleVisible: self::toBool($settings->get('theme_dark_mode_toggle_visible', true)),
        );
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
