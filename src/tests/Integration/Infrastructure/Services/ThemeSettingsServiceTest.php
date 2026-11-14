<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\ThemeSettingsServiceInterface;
use App\Infrastructure\Services\ThemeSettingsService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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

        $this->assertEquals('Roboto', $dto->fontHeading);
        $this->assertTrue($dto->darkModeDefault);
    }

    public function test_get_theme_settings_returns_defaults_when_no_settings_exist(): void
    {
        $dto = $this->service->getThemeSettings();

        $this->assertEquals('Inter', $dto->fontHeading);
        $this->assertEquals('Inter', $dto->fontBody);
        $this->assertEquals('16px', $dto->fontSizeBase);
        $this->assertEquals('0.5rem', $dto->borderRadius);
        $this->assertEquals('1', $dto->shadowIntensity);
        $this->assertEquals('solid', $dto->buttonStyle);
        $this->assertFalse($dto->darkModeDefault);
        $this->assertTrue($dto->darkModeToggleVisible);
    }

    public function test_get_css_variables_generates_valid_css_custom_properties_string(): void
    {
        $css = $this->service->getCssVariables();

        $this->assertStringContainsString(':root:root,', $css);
        $this->assertStringContainsString(':root.dark,', $css);
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

        // One step more specific than app.css (:root and .dark): the site palette wins whatever
        // order the stylesheets load in (the dev server appends app.css after the head)
        $this->assertStringStartsWith(':root:root', trim($css));
        $this->assertMatchesRegularExpression('/--[\w-]+:\s*[^;]+;/', $css);
        $this->assertStringContainsString('}', $css);
        $this->assertContains(':root.dark', $this->selectorLists($css)[1] ?? []);
    }

    public function test_an_element_marked_with_a_colour_scheme_gets_the_site_tokens_of_that_scheme(): void
    {
        $css = $this->service->getCssVariables();

        // The light block also covers an element marked dark, so it gets every variable the root
        // gets (palettes, button colours) and resolves them against its own dark tokens, as the
        // root does in dark mode. Same specificity as :root:root and :root.dark
        $this->assertSame(
            [
                [':root:root', ":root [data-color-scheme='light']", ":root [data-color-scheme='dark']"],
                [':root.dark', ":root [data-color-scheme='dark']"],
            ],
            $this->selectorLists($css),
        );
    }

    public function test_get_css_variables_includes_dark_mode_overrides(): void
    {
        $css = $this->service->getCssVariables();

        $darkModeSection = $this->extractDarkModeSection($css);

        $this->assertStringContainsString('--color-primary-action:', $darkModeSection);
        $this->assertStringContainsString('--color-bg-page:', $darkModeSection);
        $this->assertStringContainsString('--color-text-primary:', $darkModeSection);
    }

    public function test_muted_background_stands_out_from_the_surface_in_both_modes(): void
    {
        $css = $this->service->getCssVariables();

        foreach (['light' => $this->extractLightModeSection($css), 'dark' => $this->extractDarkModeSection($css)] as $mode => $section) {
            $surface = $this->cssVariable($section, 'color-bg-surface');
            $muted = $this->cssVariable($section, 'color-bg-muted');

            // Boxes and pills drawn with bg-muted on a card vanish when both resolve to the same colour
            $this->assertNotSame($surface, $muted, "bg-muted equals bg-surface in {$mode} mode");
        }
    }

    public function test_get_css_variables_includes_semantic_colors(): void
    {
        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--color-success:', $css);
        $this->assertStringContainsString('--color-error:', $css);
        $this->assertStringContainsString('--color-warning:', $css);
        $this->assertStringContainsString('--color-info:', $css);
    }

    public function test_get_css_variables_includes_tooltip_variables(): void
    {
        $css = $this->service->getCssVariables();

        $this->assertStringContainsString('--color-bg-tooltip:', $css);
        $this->assertStringContainsString('--color-text-tooltip:', $css);
        $this->assertStringContainsString('--color-text-tooltip-secondary:', $css);
        $this->assertStringContainsString('--color-ring-tooltip:', $css);

        // Verify tooltip variables exist in both light and dark sections
        $lightSection = $this->extractLightModeSection($css);
        $darkSection = $this->extractDarkModeSection($css);

        $this->assertStringContainsString('--color-bg-tooltip:', $lightSection);
        $this->assertStringContainsString('--color-bg-tooltip:', $darkSection);
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
        $this->assertStringContainsString('--shadow-intensity: 1;', $css);
    }

    public function test_get_css_variables_gives_playfair_display_a_serif_fallback(): void
    {
        $this->settingsService->set('theme_font_heading', 'Playfair Display');

        $css = $this->service->getCssVariables();

        $this->assertSame("'Playfair Display', Georgia, serif", $this->cssVariable($css, 'font-heading'));
    }

    public function test_get_css_variables_names_the_system_font_without_quotes(): void
    {
        $this->settingsService->set('theme_font_body', 'system-ui');

        $css = $this->service->getCssVariables();

        $this->assertSame('system-ui, sans-serif', $this->cssVariable($css, 'font-body'));
    }

    public function test_get_css_variables_never_writes_a_font_outside_the_list(): void
    {
        $this->settingsService->set('theme_font_heading', "x'; } body { display: none } :root { --a: '");

        $css = $this->service->getCssVariables();

        $this->assertStringNotContainsString('display: none', $css);
        $this->assertSame("'Inter', system-ui, sans-serif", $this->cssVariable($css, 'font-heading'));
    }

    /**
     * @return iterable<string, array{string, array<string, string>}>
     */
    public static function buttonStyles(): iterable
    {
        yield 'solid' => ['solid', [
            'button-primary-bg' => 'var(--color-primary)',
            'button-primary-text' => '#FFFFFF',
            'button-primary-border' => 'transparent',
            'button-primary-hover-bg' => 'var(--color-primary-700)',
            'button-primary-hover-text' => '#FFFFFF',
        ]];
        yield 'outline' => ['outline', [
            'button-primary-bg' => 'transparent',
            'button-primary-text' => 'var(--color-primary-link)',
            'button-primary-border' => 'var(--color-primary-link)',
            'button-primary-hover-bg' => 'var(--color-primary-light)',
            'button-primary-hover-text' => 'var(--color-primary-link)',
        ]];
        yield 'ghost' => ['ghost', [
            'button-primary-bg' => 'transparent',
            'button-primary-text' => 'var(--color-primary-link)',
            'button-primary-border' => 'transparent',
            'button-primary-hover-bg' => 'var(--color-primary-light)',
            'button-primary-hover-text' => 'var(--color-primary-link)',
        ]];
    }

    /**
     * @param  array<string, string>  $expected
     */
    #[DataProvider('buttonStyles')]
    public function test_get_css_variables_describes_the_primary_button_style(string $style, array $expected): void
    {
        $this->settingsService->set('theme_button_style', $style);

        $light = $this->extractLightModeSection($this->service->getCssVariables());

        foreach ($expected as $name => $value) {
            $this->assertSame($value, $this->cssVariable($light, $name), "--{$name} for {$style}");
        }
    }

    public function test_get_css_variables_includes_system_ui_fallback_fonts(): void
    {
        $this->settingsService->set('theme_font_heading', 'Roboto');
        $this->settingsService->set('theme_font_body', 'Open Sans');

        $css = $this->service->getCssVariables();

        $this->assertStringContainsString("'Roboto', system-ui, sans-serif", $css);
        $this->assertStringContainsString("'Open Sans', system-ui, sans-serif", $css);
    }

    private function cssVariable(string $section, string $name): string
    {
        if (preg_match('/--'.preg_quote($name, '/').':\s*([^;]+);/', $section, $matches) !== 1) {
            $this->fail("--{$name} is not defined");
        }

        return trim($matches[1]);
    }

    private function extractLightModeSection(string $css): string
    {
        return $this->blocks($css)[0]['body'] ?? '';
    }

    private function extractDarkModeSection(string $css): string
    {
        return $this->blocks($css)[1]['body'] ?? '';
    }

    /**
     * The selectors of each block, in order.
     *
     * @return list<list<string>>
     */
    private function selectorLists(string $css): array
    {
        return array_map(
            fn (array $block): array => array_map(trim(...), explode(',', $block['selectors'])),
            $this->blocks($css),
        );
    }

    /**
     * @return list<array{selectors: string, body: string}>
     */
    private function blocks(string $css): array
    {
        $withoutComments = (string) preg_replace('#/\*.*?\*/#s', '', $css);
        preg_match_all('/([^{}]+)\{([^}]*)\}/s', $withoutComments, $matches, PREG_SET_ORDER);

        return array_map(fn (array $match): array => ['selectors' => trim($match[1]), 'body' => $match[2]], $matches);
    }
}
