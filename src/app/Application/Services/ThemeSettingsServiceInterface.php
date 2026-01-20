<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\ThemeSettingsDTO;

interface ThemeSettingsServiceInterface
{
    /**
     * Get theme settings as a DTO.
     */
    public function getThemeSettings(): ThemeSettingsDTO;

    /**
     * Generate CSS custom properties string for theme variables.
     */
    public function getCssVariables(): string;
}
