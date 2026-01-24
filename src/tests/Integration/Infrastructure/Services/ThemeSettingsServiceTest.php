<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\DTOs\ThemeSettingsDTO;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use App\Infrastructure\Services\ThemeSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ThemeSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

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
        $this->settingsService->set('theme_primary_color', '#3B82F6');
        $this->settingsService->set('theme_primary_color_dark', '#60A5FA');
        $this->settingsService->set('theme_font_heading', 'Roboto');
        $this->settingsService->set('theme_dark_mode_default', '1');

        $dto = $this->service->getThemeSettings();

        $this->assertInstanceOf(ThemeSettingsDTO::class, $dto);
        $this->assertEquals('#3B82F6', $dto->primaryColor);
        $this->assertEquals('#60A5FA', $dto->primaryColorDark);
        $this->assertEquals('Roboto', $dto->fontHeading);
        $this->assertTrue($dto->darkModeDefault);
    }

    public function test_get_theme_settings_returns_defaults_when_no_settings_exist(): void
    {
        $dto = $this->service->getThemeSettings();

        $this->assertInstanceOf(ThemeSettingsDTO::class, $dto);
        $this->assertEquals('#D97706', $dto->primaryColor);
        $this->assertEquals('#F59E0B', $dto->primaryColorDark);
        $this->assertEquals('#57534E', $dto->secondaryColor);
        $this->assertEquals('#A8A29E', $dto->secondaryColorDark);
        $this->assertEquals('#D97706', $dto->accentColor);
        $this->assertEquals('#FAFAF9', $dto->backgroundColor);
        $this->assertEquals('#1C1917', $dto->backgroundColorDark);
        $this->assertEquals('#FFFFFF', $dto->surfaceColor);
        $this->assertEquals('#292524', $dto->surfaceColorDark);
        $this->assertEquals('#1C1917', $dto->textColor);
        $this->assertEquals('#F5F5F4', $dto->textColorDark);
        $this->assertEquals('Inter', $dto->fontHeading);
        $this->assertEquals('Inter', $dto->fontBody);
        $this->assertEquals('16px', $dto->fontSizeBase);
        $this->assertEquals('0.5rem', $dto->borderRadius);
        $this->assertEquals('subtle', $dto->shadowIntensity);
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
        $this->assertStringContainsString('--color-primary:', $css);
        $this->assertStringContainsString('--color-secondary:', $css);
        $this->assertStringContainsString('--color-accent:', $css);
        $this->assertStringContainsString('--color-background:', $css);
        $this->assertStringContainsString('--color-surface:', $css);
        $this->assertStringContainsString('--color-text:', $css);
    }

    public function test_get_css_variables_includes_light_mode_variables(): void
    {
        $this->settingsService->set('theme_primary_color', '#3B82F6');
        $this->settingsService->set('theme_background_color', '#FAFAFA');
        $this->settingsService->set('theme_text_color', '#1F2937');

        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--color-primary: #3B82F6;', $css);
        $this->assertStringContainsString('--color-background: #FAFAFA;', $css);
        $this->assertStringContainsString('--color-text: #1F2937;', $css);
    }

    public function test_get_css_variables_includes_dark_mode_variables(): void
    {
        $this->settingsService->set('theme_primary_color_dark', '#60A5FA');
        $this->settingsService->set('theme_background_color_dark', '#0F172A');
        $this->settingsService->set('theme_text_color_dark', '#FFFFFF');

        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('.dark {', $css);
        $this->assertStringContainsString('--color-primary: #60A5FA;', $css);
        $this->assertStringContainsString('--color-background: #0F172A;', $css);
        $this->assertStringContainsString('--color-text: #FFFFFF;', $css);
    }

    public function test_get_css_variables_includes_typography_variables(): void
    {
        $this->settingsService->set('theme_font_heading', 'Roboto');
        $this->settingsService->set('theme_font_body', 'Open Sans');
        $this->settingsService->set('theme_font_size_base', '18px');

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
        $this->settingsService->set('theme_border_radius', '0.75rem');
        $this->settingsService->set('theme_shadow_intensity', 'medium');

        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--border-radius:', $css);
        $this->assertStringContainsString('--shadow-intensity:', $css);
        $this->assertStringContainsString('0.75rem', $css);
        $this->assertStringContainsString('medium', $css);
    }

    public function test_get_css_variables_formats_css_correctly(): void
    {
        $css = $this->service->getCssVariables();

        $this->assertStringStartsWith(':root {', trim($css));
        $this->assertMatchesRegularExpression('/--[\w-]+:\s*[^;]+;/', $css);
        $this->assertStringContainsString('}', $css);
        $this->assertStringContainsString('.dark {', $css);
    }

    public function test_get_css_variables_includes_all_color_variables_in_light_mode(): void
    {
        $css = $this->service->getCssVariables();

        $lightModeSection = $this->extractLightModeSection($css);

        $this->assertStringContainsString('--color-primary:', $lightModeSection);
        $this->assertStringContainsString('--color-secondary:', $lightModeSection);
        $this->assertStringContainsString('--color-accent:', $lightModeSection);
        $this->assertStringContainsString('--color-background:', $lightModeSection);
        $this->assertStringContainsString('--color-surface:', $lightModeSection);
        $this->assertStringContainsString('--color-text:', $lightModeSection);
    }

    public function test_get_css_variables_includes_all_color_variables_in_dark_mode(): void
    {
        $css = $this->service->getCssVariables();

        $darkModeSection = $this->extractDarkModeSection($css);

        $this->assertStringContainsString('--color-primary:', $darkModeSection);
        $this->assertStringContainsString('--color-secondary:', $darkModeSection);
        $this->assertStringContainsString('--color-accent:', $darkModeSection);
        $this->assertStringContainsString('--color-background:', $darkModeSection);
        $this->assertStringContainsString('--color-surface:', $darkModeSection);
        $this->assertStringContainsString('--color-text:', $darkModeSection);
    }

    public function test_get_css_variables_uses_default_values_when_no_settings_exist(): void
    {
        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--color-primary: #D97706;', $css);
        $this->assertStringContainsString('--color-background: #FAFAF9;', $css);
        $this->assertStringContainsString('--color-text: #1C1917;', $css);
        $this->assertStringContainsString("--font-heading: 'Inter'", $css);
        $this->assertStringContainsString("--font-body: 'Inter'", $css);
        $this->assertStringContainsString('--font-size-base: 16px;', $css);
        $this->assertStringContainsString('--border-radius: 0.5rem;', $css);
        $this->assertStringContainsString('--shadow-intensity: subtle;', $css);
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
