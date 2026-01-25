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

        return <<<CSS
        :root {
          --color-primary: {$theme->primaryColor};
          --color-secondary: {$theme->secondaryColor};
          --color-accent: {$theme->accentColor};
          --color-background: {$theme->backgroundColor};
          --color-surface: {$theme->surfaceColor};
          --color-text: {$theme->textColor};
          --font-heading: '{$theme->fontHeading}', system-ui, sans-serif;
          --font-body: '{$theme->fontBody}', system-ui, sans-serif;
          --font-size-base: {$theme->fontSizeBase};
          --border-radius: {$theme->borderRadius};
          --shadow-intensity: {$theme->shadowIntensity};
        }

        .dark {
          --color-primary: {$theme->primaryColorDark};
          --color-secondary: {$theme->secondaryColorDark};
          --color-accent: {$theme->primaryColorDark};
          --color-background: {$theme->backgroundColorDark};
          --color-surface: {$theme->surfaceColorDark};
          --color-text: {$theme->textColorDark};
        }
        CSS;
    }
}
