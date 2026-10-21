<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Services\Color;

use App\Application\Services\ColorPaletteGeneratorInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Domain\ValueObjects\ColorPalette;
use App\Domain\ValueObjects\HexColor;
use App\Infrastructure\Services\Color\OklchColorPaletteGenerator;
use App\Infrastructure\Services\ThemeSettingsService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ThemeSettingsServicePaletteTest extends TestCase
{
    private ThemeSettingsService $service;

    private SettingsServiceInterface $settingsService;

    private ColorPaletteGeneratorInterface $paletteGenerator;

    protected function setUp(): void
    {
        $this->settingsService = $this->createStub(SettingsServiceInterface::class);
        $this->paletteGenerator = new OklchColorPaletteGenerator;
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

    #[DataProvider('themeColours')]
    public function test_text_colours_reach_aa_on_every_light_background(string $primary, string $accent): void
    {
        $this->useColours($primary, $accent);
        $neutral = $this->service->getNeutralPalette();
        $root = $this->extractRootSection($this->service->getCssVariables());

        foreach (['--color-primary-link', '--color-text-muted'] as $variable) {
            $text = $this->hexVariable($root, $variable);
            foreach ([new HexColor('#FFFFFF'), $neutral->shade(50), $neutral->shade(100)] as $background) {
                $this->assertNotNull($background);
                $this->assertGreaterThanOrEqual(4.5, $text->contrastRatio($background), "{$variable} on {$background->value}");
            }
        }
    }

    #[DataProvider('themeColours')]
    public function test_text_colours_reach_aa_on_every_dark_background(string $primary, string $accent): void
    {
        $this->useColours($primary, $accent);
        $neutral = $this->service->getNeutralPalette();
        $dark = $this->extractDarkSection($this->service->getCssVariables());

        foreach (['--color-primary-link', '--color-text-muted'] as $variable) {
            $text = $this->hexVariable($dark, $variable);
            foreach ([$neutral->shade(800), $neutral->shade(900), $neutral->shade(950)] as $background) {
                $this->assertNotNull($background);
                $this->assertGreaterThanOrEqual(4.5, $text->contrastRatio($background), "{$variable} on {$background->value}");
            }
        }
    }

    #[DataProvider('themeColours')]
    public function test_white_text_reaches_aa_on_the_primary_background_in_both_themes(string $primary, string $accent): void
    {
        $this->useColours($primary, $accent);
        $css = $this->service->getCssVariables();

        foreach (['light' => $this->extractRootSection($css), 'dark' => $this->extractDarkSection($css)] as $theme => $section) {
            $background = $this->hexVariable($section, '--color-primary');

            $this->assertGreaterThanOrEqual(4.5, (new HexColor('#FFFFFF'))->contrastRatio($background), "{$theme}: white on {$background->value}");
        }
    }

    public function test_a_primary_colour_that_already_carries_white_text_is_kept(): void
    {
        $this->useColours('#DC2626', '#F59E0B');

        $this->assertSame('#DC2626', $this->hexVariable($this->extractRootSection($this->service->getCssVariables()), '--color-primary')->value);
    }

    #[DataProvider('themeColours')]
    public function test_primary_text_reaches_aa_on_the_primary_tint_in_both_themes(string $primary, string $accent): void
    {
        $this->useColours($primary, $accent);
        $palette = $this->service->getPrimaryPalette();
        $css = $this->service->getCssVariables();

        // bg-primary-light is primary-100 in light mode and primary-950 in dark mode
        foreach ([[$this->extractRootSection($css), 100], [$this->extractDarkSection($css), 950]] as [$section, $shade]) {
            $tint = $palette->shade($shade);
            $this->assertNotNull($tint);
            $this->assertGreaterThanOrEqual(4.5, $this->hexVariable($section, '--color-primary-link')->contrastRatio($tint), "primary text on primary-{$shade}");
        }
    }

    public function test_muted_text_stays_lighter_than_secondary_text_in_light_mode(): void
    {
        $this->useColours('#DC2626', '#F59E0B');
        $secondary = $this->service->getNeutralPalette()->shade(600);
        $muted = $this->hexVariable($this->extractRootSection($this->service->getCssVariables()), '--color-text-muted');

        $this->assertNotNull($secondary);
        $this->assertGreaterThan($secondary->toOklch()['l'], $muted->toOklch()['l']);
    }

    /**
     * The theme presets offered in the panel, plus the default colours.
     *
     * @return array<string, array{string, string}>
     */
    public static function themeColours(): array
    {
        return [
            'default' => ['#D97706', '#0EA5E9'],
            'dragon flames' => ['#DC2626', '#F59E0B'],
            'elven forest' => ['#059669', '#84CC16'],
            'ocean depths' => ['#0284C7', '#06B6D4'],
            'royal purple' => ['#9333EA', '#EC4899'],
            'shadow realm' => ['#475569', '#8B5CF6'],
            'forge steel' => ['#0F766E', '#FB923C'],
        ];
    }

    private function useColours(string $primary, string $accent): void
    {
        $this->settingsService->method('get')->willReturnCallback(
            fn (string $key, mixed $default = null): mixed => match ($key) {
                'theme_primary_base_color' => $primary,
                'theme_accent_base_color' => $accent,
                default => $default,
            }
        );
    }

    private function hexVariable(string $section, string $variable): HexColor
    {
        $this->assertMatchesRegularExpression('/'.preg_quote($variable, '/').':\s*#[0-9A-F]{6};/', $section, "{$variable} must be a computed colour");
        preg_match('/'.preg_quote($variable, '/').':\s*(#[0-9A-F]{6});/', $section, $matches);

        return new HexColor($matches[1]);
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
