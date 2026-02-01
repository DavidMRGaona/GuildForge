<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\ThemeSettingsDTO;
use App\Application\Services\ColorPaletteGeneratorInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use App\Domain\ValueObjects\ColorPalette;
use App\Domain\ValueObjects\HexColor;
use App\Infrastructure\Services\Color\OklchColorPaletteGenerator;

final readonly class ThemeSettingsService implements ThemeSettingsServiceInterface
{
    private const string DEFAULT_PRIMARY_COLOR = '#D97706';

    private const string DEFAULT_ACCENT_COLOR = '#0EA5E9';

    private ColorPaletteGeneratorInterface $paletteGenerator;

    public function __construct(
        private SettingsServiceInterface $settingsService,
        ?ColorPaletteGeneratorInterface $paletteGenerator = null,
    ) {
        $this->paletteGenerator = $paletteGenerator ?? new OklchColorPaletteGenerator();
    }

    public function getThemeSettings(): ThemeSettingsDTO
    {
        return ThemeSettingsDTO::fromSettings($this->settingsService);
    }

    public function getCssVariables(): string
    {
        $theme = $this->getThemeSettings();
        $primaryPalette = $this->getPrimaryPalette();
        $accentPalette = $this->getAccentPalette();
        $neutralPalette = $this->getNeutralPalette();

        // Generate palette CSS
        $primaryCss = $this->generatePaletteCss($primaryPalette);
        $accentCss = $this->generatePaletteCss($accentPalette);
        $neutralCss = $this->generatePaletteCss($neutralPalette);

        return <<<CSS
        :root {
          /* Primary palette */
          {$primaryCss}

          /* Accent palette */
          {$accentCss}

          /* Neutral palette */
          {$neutralCss}

          /* Contextual variables (Light mode) */
          --color-primary-action: var(--color-primary-600);
          --color-primary-action-hover: var(--color-primary-700);
          --color-primary-link: var(--color-primary-700);
          --color-primary-subtle: var(--color-primary-100);
          --color-primary-badge: var(--color-primary-200);
          --color-primary-glow: var(--color-primary-500);

          /* Surface variables (Light mode) */
          --color-bg-page: var(--color-neutral-50);
          --color-bg-surface: #FFFFFF;
          --color-bg-muted: var(--color-neutral-100);
          --color-bg-elevated: #FFFFFF;

          /* Text variables (Light mode) */
          --color-text-primary: var(--color-neutral-900);
          --color-text-secondary: var(--color-neutral-600);
          --color-text-muted: var(--color-neutral-400);

          /* Border variables (Light mode) */
          --color-border: var(--color-neutral-200);
          --color-border-strong: var(--color-neutral-300);

          /* Semantic colors (fixed for universal recognition) */
          --color-success: #16A34A;
          --color-success-bg: #DCFCE7;
          --color-error: #DC2626;
          --color-error-bg: #FEE2E2;
          --color-warning: #CA8A04;
          --color-warning-bg: #FEF9C3;
          --color-info: #2563EB;
          --color-info-bg: #DBEAFE;

          /* Typography */
          --font-heading: '{$theme->fontHeading}', system-ui, sans-serif;
          --font-body: '{$theme->fontBody}', system-ui, sans-serif;
          --font-size-base: {$theme->fontSizeBase};

          /* UI */
          --border-radius: {$theme->borderRadius};
          --shadow-intensity: {$theme->shadowIntensity};
        }

        .dark {
          /* Contextual variables (Dark mode) */
          --color-primary-action: var(--color-primary-500);
          --color-primary-action-hover: var(--color-primary-400);
          --color-primary-link: var(--color-primary-400);
          --color-primary-subtle: var(--color-primary-950);
          --color-primary-badge: var(--color-primary-900);
          --color-primary-glow: var(--color-primary-400);

          /* Surface variables (Dark mode) */
          --color-bg-page: var(--color-neutral-950);
          --color-bg-surface: var(--color-neutral-900);
          --color-bg-muted: var(--color-neutral-900);
          --color-bg-elevated: var(--color-neutral-800);

          /* Text variables (Dark mode) */
          --color-text-primary: var(--color-neutral-50);
          --color-text-secondary: var(--color-neutral-300);
          --color-text-muted: var(--color-neutral-500);

          /* Border variables (Dark mode) */
          --color-border: var(--color-neutral-700);
          --color-border-strong: var(--color-neutral-600);

          /* Semantic colors (Dark mode) */
          --color-success: #4ADE80;
          --color-success-bg: #14532D;
          --color-error: #F87171;
          --color-error-bg: #7F1D1D;
          --color-warning: #FACC15;
          --color-warning-bg: #713F12;
          --color-info: #60A5FA;
          --color-info-bg: #1E3A8A;
        }
        CSS;
    }

    /**
     * Get the primary color palette.
     */
    public function getPrimaryPalette(): ColorPalette
    {
        $baseColor = $this->getPrimaryBaseColor();

        return $this->paletteGenerator->generate('primary', $baseColor);
    }

    /**
     * Get the accent color palette.
     */
    public function getAccentPalette(): ColorPalette
    {
        $baseColor = $this->getAccentBaseColor();

        return $this->paletteGenerator->generate('accent', $baseColor);
    }

    /**
     * Get the neutral color palette (derived from accent).
     */
    public function getNeutralPalette(): ColorPalette
    {
        $accentColor = $this->getAccentBaseColor();

        return $this->paletteGenerator->generateNeutral('neutral', $accentColor);
    }

    private function getPrimaryBaseColor(): HexColor
    {
        $color = $this->settingsService->get('theme_primary_base_color', self::DEFAULT_PRIMARY_COLOR);

        return new HexColor((string) $color ?: self::DEFAULT_PRIMARY_COLOR);
    }

    private function getAccentBaseColor(): HexColor
    {
        $color = $this->settingsService->get('theme_accent_base_color', self::DEFAULT_ACCENT_COLOR);

        return new HexColor((string) $color ?: self::DEFAULT_ACCENT_COLOR);
    }

    private function generatePaletteCss(ColorPalette $palette): string
    {
        $lines = [];

        foreach ($palette->shades() as $shade => $color) {
            $lines[] = "--color-{$palette->name()}-{$shade}: {$color->value};";
        }

        return implode("\n          ", $lines);
    }
}
