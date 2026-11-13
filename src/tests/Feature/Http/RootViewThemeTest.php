<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Application\Services\SettingsServiceInterface;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The root view picks the theme before the first paint: the layout only applies it once Vue
 * has mounted, so a full page load in dark mode would otherwise flash the light palette.
 */
final class RootViewThemeTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_head_applies_the_theme_before_the_stylesheets_and_scripts_load(): void
    {
        $head = $this->rootHead();

        $script = strpos($head, 'data-theme-boot');
        $this->assertNotFalse($script, 'The head carries no theme script.');
        $this->assertStringContainsString("localStorage.getItem('themeMode')", $head);
        $this->assertStringContainsString('(prefers-color-scheme: dark)', $head);
        $this->assertStringContainsString("classList.toggle('dark'", $head);

        $assets = strpos($head, '<link rel="stylesheet"');
        if ($assets !== false) {
            $this->assertLessThan($assets, $script);
        }
    }

    public function test_without_a_stored_choice_the_script_follows_the_system_by_default(): void
    {
        $this->assertStringContainsString('var darkByDefault = false;', $this->rootHead());
    }

    public function test_without_a_stored_choice_the_script_follows_the_site_default_when_it_is_dark(): void
    {
        app(SettingsServiceInterface::class)->set('theme_dark_mode_default', '1');

        $this->assertStringContainsString('var darkByDefault = true;', $this->rootHead());
    }

    /**
     * The layouts add the site palette only once Vue mounts them, and module pages without the
     * core layout get no layout at all: the head carries it for every page (core#394).
     */
    public function test_the_head_carries_the_site_palette(): void
    {
        $head = $this->rootHead();

        $this->assertMatchesRegularExpression('#<style data-site-theme>\s*:root:root \{#', $head);
        $this->assertStringContainsString('--color-primary-700:', $head);
        $this->assertStringContainsString('--color-primary-action:', $head);
        $this->assertStringContainsString(':root.dark {', $head);
    }

    public function test_the_head_loads_the_site_fonts(): void
    {
        app(SettingsServiceInterface::class)->set('theme_font_heading', 'Playfair Display');
        app(SettingsServiceInterface::class)->set('theme_font_body', 'Open Sans');

        $this->assertStringContainsString(
            '<link rel="stylesheet" href="https://fonts.bunny.net/css2?family=Playfair%20Display:wght@400;500;600;700&amp;family=Open%20Sans:wght@400;500;600;700&amp;display=swap" data-site-fonts>',
            $this->rootHead(),
        );
    }

    public function test_system_fonts_load_nothing(): void
    {
        app(SettingsServiceInterface::class)->set('theme_font_heading', 'system-ui');
        app(SettingsServiceInterface::class)->set('theme_font_body', 'system-ui');

        $head = $this->rootHead();

        $this->assertStringNotContainsString('data-site-fonts', $head);
        // No fixed family either: the page downloads only what the settings choose
        $this->assertStringNotContainsString('fonts.bunny.net/css', $head);
    }

    public function test_a_stored_value_cannot_close_the_style_element(): void
    {
        app(SettingsServiceInterface::class)->set('theme_font_heading', 'Lora</style><script>alert(1)</script>');

        $head = $this->rootHead();

        $this->assertStringNotContainsString('<script>alert(1)</script>', $head);
        $this->assertSame(1, substr_count($head, '</style>'));
    }

    private function rootHead(): string
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertIsString($html);

        if (preg_match('#<head>(.*?)</head>#s', $html, $m) !== 1) {
            $this->fail('The root view has no head.');
        }

        return $m[1];
    }
}
