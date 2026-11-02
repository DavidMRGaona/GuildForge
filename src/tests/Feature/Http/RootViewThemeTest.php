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
