<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\DTOs\ThemeSettingsDTO;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use App\Infrastructure\Services\ThemeSettingsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class ThemeSettingsServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private ThemeSettingsServiceInterface $service;

    private SettingsServiceInterface $settingsService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->settingsService = app(SettingsServiceInterface::class);
        $this->service = new ThemeSettingsService($this->settingsService);
    }

    public function test_get_theme_settings_returns_dto_with_values_from_settings(): void
    {
        $this->settingsService->set('theme_font_heading', 'Roboto');
        $this->settingsService->set('theme_dark_mode_default', '1');

        $dto = $this->service->getThemeSettings();

        $this->assertInstanceOf(ThemeSettingsDTO::class, $dto);
        $this->assertEquals('Roboto', $dto->fontHeading);
        $this->assertTrue($dto->darkModeDefault);
    }

    public function test_get_theme_settings_returns_defaults_when_no_settings_exist(): void
    {
        $dto = $this->service->getThemeSettings();

        $this->assertInstanceOf(ThemeSettingsDTO::class, $dto);
        $this->assertEquals('Inter', $dto->fontHeading);
        $this->assertEquals('Inter', $dto->fontBody);
        $this->assertEquals('16px', $dto->fontSizeBase);
        $this->assertEquals('0.5rem', $dto->borderRadius);
        $this->assertEquals('0.5', $dto->shadowIntensity);
        $this->assertEquals('solid', $dto->buttonStyle);
        $this->assertFalse($dto->darkModeDefault);
        $this->assertTrue($dto->darkModeToggleVisible);
    }

    public function test_get_css_variables_generates_valid_css_custom_properties_string(): void
    {
        $css = $this->service->getCssVariables();

        $this->assertIsString($css);
        $this->assertStringContainsString(':root {', $css);
        $this->assertStringContainsString('.dark {', $css);
        // New palette variables
        $this->assertStringContainsString('--color-primary-500:', $css);
        $this->assertStringContainsString('--color-accent-500:', $css);
        $this->assertStringContainsString('--color-neutral-500:', $css);
    }

    public function test_get_css_variables_includes_palette_shades(): void
    {
        $this->settingsService->set('theme_primary_base_color', '#3B82F6');

        $css = $this->service->getCssVariables();

        // Primary palette shades
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

    public function test_get_css_variables_includes_contextual_variables(): void
    {
        $css = $this->service->getCssVariables();

        $lightModeSection = $this->extractLightModeSection($css);

        $this->assertStringContainsString('--color-primary-action:', $lightModeSection);
        $this->assertStringContainsString('--color-primary-action-hover:', $lightModeSection);
        $this->assertStringContainsString('--color-primary-link:', $lightModeSection);
        $this->assertStringContainsString('--color-primary-subtle:', $lightModeSection);
    }

    public function test_get_css_variables_includes_surface_variables(): void
    {
        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--color-bg-page:', $css);
        $this->assertStringContainsString('--color-bg-surface:', $css);
        $this->assertStringContainsString('--color-bg-muted:', $css);
        $this->assertStringContainsString('--color-bg-elevated:', $css);
    }

    public function test_get_css_variables_includes_typography_variables(): void
    {
        $this->settingsService->set('theme_font_heading', 'Roboto');
        $this->settingsService->set('theme_font_body', 'Open Sans');
        $this->settingsService->set('theme_font_size_base', 'large');

        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--font-heading:', $css);
        $this->assertStringContainsString('--font-body:', $css);
        $this->assertStringContainsString('--font-size-base:', $css);
        $this->assertStringContainsString("'Roboto'", $css);
        $this->assertStringContainsString("'Open Sans'", $css);
        $this->assertStringContainsString('18px', $css);
    }

    public function test_get_css_variables_includes_appearance_variables(): void
    {
        $this->settingsService->set('theme_border_radius', 'large');
        $this->settingsService->set('theme_shadow_intensity', 'medium');

        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--border-radius:', $css);
        $this->assertStringContainsString('--shadow-intensity:', $css);
        $this->assertStringContainsString('0.75rem', $css);
        $this->assertStringContainsString('1', $css);
    }

    public function test_get_css_variables_formats_css_correctly(): void
    {
        $css = $this->service->getCssVariables();

        $this->assertStringStartsWith(':root {', trim($css));
        $this->assertMatchesRegularExpression('/--[\w-]+:\s*[^;]+;/', $css);
        $this->assertStringContainsString('}', $css);
        $this->assertStringContainsString('.dark {', $css);
    }

    public function test_get_css_variables_includes_dark_mode_overrides(): void
    {
        $css = $this->service->getCssVariables();

        $darkModeSection = $this->extractDarkModeSection($css);

        $this->assertStringContainsString('--color-primary-action:', $darkModeSection);
        $this->assertStringContainsString('--color-bg-page:', $darkModeSection);
        $this->assertStringContainsString('--color-text-primary:', $darkModeSection);
    }

    public function test_get_css_variables_includes_semantic_colors(): void
    {
        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--color-success:', $css);
        $this->assertStringContainsString('--color-error:', $css);
        $this->assertStringContainsString('--color-warning:', $css);
        $this->assertStringContainsString('--color-info:', $css);
    }

    public function test_get_css_variables_uses_default_colors_when_no_settings_exist(): void
    {
        $css = $this->service->getCssVariables();

        // Default amber color #D97706 should be used for palette generation
        $this->assertStringContainsString('#D97706', $css);
        $this->assertStringContainsString("--font-heading: 'Inter'", $css);
        $this->assertStringContainsString("--font-body: 'Inter'", $css);
        $this->assertStringContainsString('--font-size-base: 16px;', $css);
        $this->assertStringContainsString('--border-radius: 0.5rem;', $css);
        $this->assertStringContainsString('--shadow-intensity: 0.5;', $css);
    }

    public function test_get_css_variables_includes_system_ui_fallback_fonts(): void
    {
        $this->settingsService->set('theme_font_heading', 'Roboto');
        $this->settingsService->set('theme_font_body', 'Open Sans');

        $css = $this->service->getCssVariables();

        $this->assertStringContainsString("'Roboto', system-ui, sans-serif", $css);
        $this->assertStringContainsString("'Open Sans', system-ui, sans-serif", $css);
    }

    private function extractLightModeSection(string $css): string
    {
        $pattern = '/:root\s*\{([^}]+)\}/s';
        preg_match($pattern, $css, $matches);

        return $matches[1] ?? '';
    }

    private function extractDarkModeSection(string $css): string
    {
        $pattern = '/\.dark\s*\{([^}]+)\}/s';
        preg_match($pattern, $css, $matches);

        return $matches[1] ?? '';
    }
}
