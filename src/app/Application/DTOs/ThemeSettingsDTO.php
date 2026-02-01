<?php

declare(strict_types=1);

namespace App\Application\DTOs;

use App\Application\Services\SettingsServiceInterface;

final readonly class ThemeSettingsDTO
{
    public function __construct(
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
