<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\ThemeSettingsDTO;
use App\Domain\ValueObjects\ColorPalette;

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

    /**
     * Get the primary color palette.
     */
    public function getPrimaryPalette(): ColorPalette;

    /**
     * Get the accent color palette.
     */
    public function getAccentPalette(): ColorPalette;

    /**
     * Get the neutral color palette (derived from accent).
     */
    public function getNeutralPalette(): ColorPalette;
}
