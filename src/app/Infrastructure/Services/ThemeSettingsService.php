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
        $this->paletteGenerator = $paletteGenerator ?? new OklchColorPaletteGenerator;
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

        // Text colours computed per site: the palette shades alone fall below WCAG AA for some
        // base colours (amber, green, sky…) on the page and muted backgrounds
        $lightBackgrounds = [new HexColor('#FFFFFF'), $this->shade($neutralPalette, 50), $this->shade($neutralPalette, 100)];
        $darkBackgrounds = [$this->shade($neutralPalette, 800), $this->shade($neutralPalette, 900), $this->shade($neutralPalette, 950)];
        // Primary text also sits on the primary tint (bg-primary-light: shade 100 light, 950 dark)
        $primaryLinkLight = $this->shade($primaryPalette, 600)->readableOn([...$lightBackgrounds, $this->shade($primaryPalette, 100)], lighten: false)->value;
        $primaryLinkDark = $this->shade($primaryPalette, 500)->readableOn([...$darkBackgrounds, $this->shade($primaryPalette, 950)], lighten: true)->value;
        $mutedLight = $this->shade($neutralPalette, 400)->readableOn($lightBackgrounds, lighten: false)->value;
        $mutedDark = $this->shade($neutralPalette, 500)->readableOn($darkBackgrounds, lighten: true)->value;
        // Buttons and other primary backgrounds carry white text in both themes
        $white = [new HexColor('#FFFFFF')];
        $primaryLight = $this->shade($primaryPalette, 600)->readableOn($white, lighten: false)->value;
        $primaryDark = $this->shade($primaryPalette, 500)->readableOn($white, lighten: false)->value;

        $fontHeading = $this->fontStack($theme->fontHeading);
        $fontBody = $this->fontStack($theme->fontBody);
        $button = $this->primaryButtonCss($theme->buttonStyle);

        // One step more specific than the defaults in app.css (:root and .dark), so the site palette
        // wins whatever order the stylesheets load in: the dev server appends app.css after the head
        return <<<CSS
        :root:root {
          /* Primary palette */
          {$primaryCss}

          /* Accent palette */
          {$accentCss}

          /* Neutral palette */
          {$neutralCss}

          /* Semantic aliases (override app.css hardcoded values) */
          --color-primary: {$primaryLight};
          --color-primary-hover: var(--color-primary-700);
          --color-primary-light: var(--color-primary-100);
          --color-primary-light-hover: var(--color-primary-200);
          --color-primary-dark: var(--color-primary-800);

          /* Contextual variables (Light mode) */
          --color-primary-action: var(--color-primary-600);
          --color-primary-action-hover: var(--color-primary-700);
          --color-primary-link: {$primaryLinkLight};
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
          --color-text-muted: {$mutedLight};

          /* Border variables (Light mode) */
          --color-border: var(--color-neutral-200);
          --color-border-strong: var(--color-neutral-300);

          /* Tooltip variables (inverted: dark in light, light in dark) */
          --color-bg-tooltip: var(--color-neutral-900);
          --color-text-tooltip: #FFFFFF;
          --color-text-tooltip-secondary: var(--color-neutral-300);
          --color-ring-tooltip: rgb(255 255 255 / 0.1);

          /* Semantic colors (fixed for universal recognition) */
          --color-success: #16A34A;
          --color-success-bg: #DCFCE7;
          --color-error: #DC2626;
          --color-error-bg: #FEE2E2;
          --color-warning: #CA8A04;
          --color-warning-bg: #FEF9C3;
          --color-info: #2563EB;
          --color-info-bg: #DBEAFE;

          /* Calendar source colors (Light mode) */
          --color-calendar-primary: var(--color-primary);
          --color-calendar-accent: var(--color-accent-400);
          --color-calendar-accent-text: var(--color-neutral-900);
          --color-calendar-info: var(--color-info);
          --color-calendar-success: var(--color-success);
          --color-calendar-warning: var(--color-warning);
          --color-calendar-neutral: var(--color-neutral-400);

          /* Typography */
          --font-heading: {$fontHeading};
          --font-body: {$fontBody};
          --font-size-base: {$theme->fontSizeBase};

          /* UI */
          --border-radius: {$theme->borderRadius};
          --shadow-intensity: {$theme->shadowIntensity};

          /* Primary button ({$theme->buttonStyle}): BaseButton reads these, in both themes */
          {$button}
        }

        :root.dark {
          /* Semantic aliases (override app.css hardcoded values) */
          --color-primary: {$primaryDark};
          --color-primary-hover: var(--color-primary-400);
          --color-primary-light: var(--color-primary-950);
          --color-primary-light-hover: var(--color-primary-900);
          --color-primary-dark: var(--color-primary-600);

          /* Contextual variables (Dark mode) */
          --color-primary-action: var(--color-primary-500);
          --color-primary-action-hover: var(--color-primary-400);
          --color-primary-link: {$primaryLinkDark};
          --color-primary-subtle: var(--color-primary-950);
          --color-primary-badge: var(--color-primary-900);
          --color-primary-glow: var(--color-primary-400);

          /* Surface variables (Dark mode) */
          --color-bg-page: var(--color-neutral-950);
          --color-bg-surface: var(--color-neutral-900);
          --color-bg-muted: var(--color-neutral-800);
          --color-bg-elevated: var(--color-neutral-800);

          /* Text variables (Dark mode) */
          --color-text-primary: var(--color-neutral-50);
          --color-text-secondary: var(--color-neutral-300);
          --color-text-muted: {$mutedDark};

          /* Border variables (Dark mode) */
          --color-border: var(--color-neutral-700);
          --color-border-strong: var(--color-neutral-600);

          /* Tooltip variables (inverted) */
          --color-bg-tooltip: var(--color-neutral-100);
          --color-text-tooltip: var(--color-neutral-900);
          --color-text-tooltip-secondary: var(--color-neutral-600);
          --color-ring-tooltip: rgb(28 25 23 / 0.1);

          /* Semantic colors (Dark mode) */
          --color-success: #4ADE80;
          --color-success-bg: #14532D;
          --color-error: #F87171;
          --color-error-bg: #7F1D1D;
          --color-warning: #FACC15;
          --color-warning-bg: #713F12;
          --color-info: #60A5FA;
          --color-info-bg: #1E3A8A;

          /* Calendar source colors (Dark mode) */
          --color-calendar-primary: var(--color-primary);
          --color-calendar-accent: var(--color-accent-300);
          --color-calendar-accent-text: var(--color-neutral-950);
          --color-calendar-info: var(--color-info);
          --color-calendar-success: var(--color-success);
          --color-calendar-warning: var(--color-warning);
          --color-calendar-neutral: var(--color-neutral-400);
        }
        CSS;
    }

    /**
     * The family and its fallbacks. Only names from ThemeSettingsDTO's lists reach this point.
     */
    private function fontStack(string $font): string
    {
        if ($font === ThemeSettingsDTO::SYSTEM_FONT) {
            return 'system-ui, sans-serif';
        }

        if (in_array($font, ThemeSettingsDTO::SERIF_FONTS, true)) {
            return "'{$font}', Georgia, serif";
        }

        return "'{$font}', system-ui, sans-serif";
    }

    /**
     * Colours of the primary button for the chosen style. They point at the palette variables, so
     * the dark theme (which redefines those on the same element) needs no copy of its own. The
     * outline and ghost text uses the link colour, already checked for 4.5:1 on the page, surface,
     * muted and primary-light backgrounds.
     */
    private function primaryButtonCss(string $style): string
    {
        $values = match ($style) {
            'outline' => ['transparent', 'var(--color-primary-link)', 'var(--color-primary-link)', 'var(--color-primary-light)', 'var(--color-primary-link)'],
            'ghost' => ['transparent', 'var(--color-primary-link)', 'transparent', 'var(--color-primary-light)', 'var(--color-primary-link)'],
            default => ['var(--color-primary)', '#FFFFFF', 'transparent', 'var(--color-primary-700)', '#FFFFFF'],
        };

        [$background, $text, $border, $hoverBackground, $hoverText] = $values;

        return implode("\n  ", [
            "--button-primary-bg: {$background};",
            "--button-primary-text: {$text};",
            "--button-primary-border: {$border};",
            "--button-primary-hover-bg: {$hoverBackground};",
            "--button-primary-hover-text: {$hoverText};",
        ]);
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

    private function shade(ColorPalette $palette, int $shade): HexColor
    {
        return $palette->shade($shade) ?? $palette->baseColor();
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
