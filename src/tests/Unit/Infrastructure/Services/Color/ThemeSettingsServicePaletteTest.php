<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services\Color;

use App\Application\Services\ColorPaletteGeneratorInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\ValueObjects\ColorPalette;
use App\Domain\ValueObjects\HexColor;
use App\Infrastructure\Services\Color\OklchColorPaletteGenerator;
use App\Infrastructure\Services\ThemeSettingsService;
use PHPUnit\Framework\TestCase;

final class ThemeSettingsServicePaletteTest extends TestCase
{
    private ThemeSettingsService $service;

    private SettingsServiceInterface $settingsService;

    private ColorPaletteGeneratorInterface $paletteGenerator;

    protected function setUp(): void
    {
        $this->settingsService = $this->createMock(SettingsServiceInterface::class);
        $this->paletteGenerator = new OklchColorPaletteGenerator();
        $this->service = new ThemeSettingsService($this->settingsService, $this->paletteGenerator);
    }

    public function test_get_css_variables_includes_primary_palette_shades(): void
    {
        $this->settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => match ($key) {
                'theme_primary_base_color' => '#D97706',
                default => $default,
            }
        );

        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--color-primary-50:', $css);
        $this->assertStringContainsString('--color-primary-100:', $css);
        $this->assertStringContainsString('--color-primary-200:', $css);
        $this->assertStringContainsString('--color-primary-300:', $css);
        $this->assertStringContainsString('--color-primary-400:', $css);
        $this->assertStringContainsString('--color-primary-500:', $css);
        $this->assertStringContainsString('--color-primary-600:', $css);
        $this->assertStringContainsString('--color-primary-700:', $css);
        $this->assertStringContainsString('--color-primary-800:', $css);
        $this->assertStringContainsString('--color-primary-900:', $css);
        $this->assertStringContainsString('--color-primary-950:', $css);
    }

    public function test_get_css_variables_includes_accent_palette_shades(): void
    {
        $this->settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => match ($key) {
                'theme_accent_base_color' => '#0EA5E9',
                default => $default,
            }
        );

        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--color-accent-50:', $css);
        $this->assertStringContainsString('--color-accent-600:', $css);
        $this->assertStringContainsString('--color-accent-950:', $css);
    }

    public function test_get_css_variables_includes_neutral_palette_shades(): void
    {
        $this->settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => match ($key) {
                'theme_accent_base_color' => '#0EA5E9',
                default => $default,
            }
        );

        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--color-neutral-50:', $css);
        $this->assertStringContainsString('--color-neutral-600:', $css);
        $this->assertStringContainsString('--color-neutral-950:', $css);
    }

    public function test_get_css_variables_includes_contextual_variables_for_light_mode(): void
    {
        $this->settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => $default
        );

        $css = $this->service->getCssVariables();
        $rootSection = $this->extractRootSection($css);

        // Contextual variables should map to appropriate shades
        $this->assertStringContainsString('--color-primary-action:', $rootSection);
        $this->assertStringContainsString('--color-primary-action-hover:', $rootSection);
        $this->assertStringContainsString('--color-primary-link:', $rootSection);
        $this->assertStringContainsString('--color-primary-subtle:', $rootSection);
    }

    public function test_get_css_variables_includes_contextual_variables_for_dark_mode(): void
    {
        $this->settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => $default
        );

        $css = $this->service->getCssVariables();
        $darkSection = $this->extractDarkSection($css);

        $this->assertStringContainsString('--color-primary-action:', $darkSection);
        $this->assertStringContainsString('--color-primary-action-hover:', $darkSection);
        $this->assertStringContainsString('--color-primary-link:', $darkSection);
    }

    public function test_get_css_variables_includes_semantic_colors(): void
    {
        $this->settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => $default
        );

        $css = $this->service->getCssVariables();

        // Semantic colors should always be present
        $this->assertStringContainsString('--color-success:', $css);
        $this->assertStringContainsString('--color-error:', $css);
        $this->assertStringContainsString('--color-warning:', $css);
        $this->assertStringContainsString('--color-info:', $css);
        $this->assertStringContainsString('--color-success-bg:', $css);
        $this->assertStringContainsString('--color-error-bg:', $css);
    }

    public function test_get_css_variables_includes_surface_variables(): void
    {
        $this->settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => $default
        );

        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--color-bg-page:', $css);
        $this->assertStringContainsString('--color-bg-surface:', $css);
        $this->assertStringContainsString('--color-bg-muted:', $css);
        $this->assertStringContainsString('--color-bg-elevated:', $css);
    }

    public function test_get_css_variables_uses_default_colors_when_not_set(): void
    {
        $this->settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => $default
        );

        $css = $this->service->getCssVariables();

        // Should use default amber color
        $this->assertStringContainsString('#D97706', $css);
    }

    public function test_get_primary_palette_returns_color_palette(): void
    {
        $this->settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => match ($key) {
                'theme_primary_base_color' => '#3B82F6',
                default => $default,
            }
        );

        $palette = $this->service->getPrimaryPalette();

        $this->assertInstanceOf(ColorPalette::class, $palette);
        $this->assertEquals('primary', $palette->name());
    }

    public function test_get_accent_palette_returns_color_palette(): void
    {
        $this->settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => match ($key) {
                'theme_accent_base_color' => '#10B981',
                default => $default,
            }
        );

        $palette = $this->service->getAccentPalette();

        $this->assertInstanceOf(ColorPalette::class, $palette);
        $this->assertEquals('accent', $palette->name());
    }

    public function test_get_neutral_palette_returns_color_palette(): void
    {
        $this->settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => $default
        );

        $palette = $this->service->getNeutralPalette();

        $this->assertInstanceOf(ColorPalette::class, $palette);
        $this->assertEquals('neutral', $palette->name());
    }

    private function extractRootSection(string $css): string
    {
        if (preg_match('/:root\s*\{([^}]+)\}/s', $css, $matches)) {
            return $matches[1];
        }

        return '';
    }

    private function extractDarkSection(string $css): string
    {
        if (preg_match('/\.dark\s*\{([^}]+)\}/s', $css, $matches)) {
            return $matches[1];
        }

        return '';
    }
}
