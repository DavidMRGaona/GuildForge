<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\ThemeSettingsDTO;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;

final readonly class ThemeSettingsService implements ThemeSettingsServiceInterface
{
    public function __construct(
        private SettingsServiceInterface $settingsService,
    ) {
    }

    public function getThemeSettings(): ThemeSettingsDTO
    {
        return ThemeSettingsDTO::fromSettings($this->settingsService);
    }

    public function getCssVariables(): string
    {
        $theme = $this->getThemeSettings();

        // Generate hover variants by darkening primary color
        $primaryHover = $this->adjustBrightness($theme->primaryColor, -15);
        $primaryHoverDark = $this->adjustBrightness($theme->primaryColorDark, 15);

        return <<<CSS
        :root {
          /* Legacy variables (backward compatibility) */
          --color-primary: {$theme->primaryColor};
          --color-secondary: {$theme->secondaryColor};
          --color-accent: {$theme->accentColor};
          --color-background: {$theme->backgroundColor};
          --color-surface: {$theme->surfaceColor};
          --color-text: {$theme->textColor};

          /* Semantic variables (used by utility classes in app.css) */
          --color-primary-hover: {$primaryHover};
          --color-bg-page: {$theme->backgroundColor};
          --color-bg-surface: {$theme->surfaceColor};
          --color-bg-muted: {$theme->backgroundColor};
          --color-text-primary: {$theme->textColor};
          --color-text-secondary: {$theme->textSecondaryColor};
          --color-text-muted: {$theme->textMutedColor};
          --color-border: {$theme->borderColor};
          --color-border-strong: {$theme->borderColor};

          /* Typography */
          --font-heading: '{$theme->fontHeading}', system-ui, sans-serif;
          --font-body: '{$theme->fontBody}', system-ui, sans-serif;
          --font-size-base: {$theme->fontSizeBase};

          /* UI */
          --border-radius: {$theme->borderRadius};
          --shadow-intensity: {$theme->shadowIntensity};
        }

        .dark {
          /* Legacy variables (backward compatibility) */
          --color-primary: {$theme->primaryColorDark};
          --color-secondary: {$theme->secondaryColorDark};
          --color-accent: {$theme->primaryColorDark};
          --color-background: {$theme->backgroundColorDark};
          --color-surface: {$theme->surfaceColorDark};
          --color-text: {$theme->textColorDark};

          /* Semantic variables (used by utility classes in app.css) */
          --color-primary-hover: {$primaryHoverDark};
          --color-bg-page: {$theme->backgroundColorDark};
          --color-bg-surface: {$theme->surfaceColorDark};
          --color-bg-muted: {$theme->surfaceColorDark};
          --color-text-primary: {$theme->textColorDark};
          --color-text-secondary: {$theme->textSecondaryColorDark};
          --color-text-muted: {$theme->textMutedColorDark};
          --color-border: {$theme->borderColorDark};
          --color-border-strong: {$theme->borderColorDark};
        }
        CSS;
    }

    /**
     * Adjust the brightness of a hex color.
     *
     * @param string $hexColor The hex color (e.g., #D97706)
     * @param int $percent Positive to lighten, negative to darken
     */
    private function adjustBrightness(string $hexColor, int $percent): string
    {
        $hex = ltrim($hexColor, '#');

        // Convert to RGB
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));

        // Adjust brightness
        $r = max(0, min(255, $r + (int) ($r * $percent / 100)));
        $g = max(0, min(255, $g + (int) ($g * $percent / 100)));
        $b = max(0, min(255, $b + (int) ($b * $percent / 100)));

        return sprintf('#%02X%02X%02X', $r, $g, $b);
    }
}
