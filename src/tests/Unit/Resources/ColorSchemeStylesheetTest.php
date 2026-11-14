<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;

/**
 * An element marked with data-color-scheme gets the default tokens of that scheme from app.css,
 * whatever the page's mode (core#640). The site palette does the same in ThemeSettingsService.
 */
final class ColorSchemeStylesheetTest extends TestCase
{
    public function test_light_tokens_also_apply_to_an_element_marked_with_either_scheme(): void
    {
        $this->assertSame(
            [':root', "[data-color-scheme='light']", "[data-color-scheme='dark']"],
            $this->selectorsOfTheBlockDeclaring('--color-bg-surface: #ffffff;'),
        );
    }

    public function test_dark_tokens_also_apply_to_an_element_marked_dark(): void
    {
        $this->assertSame(
            ['.dark', "[data-color-scheme='dark']"],
            $this->selectorsOfTheBlockDeclaring('--color-text-inverted: #1c1917;'),
        );
    }

    public function test_native_controls_follow_the_marked_scheme(): void
    {
        $this->assertSame([':root', "[data-color-scheme='light']"], $this->selectorsOfTheBlockDeclaring('color-scheme: light;'));
        $this->assertSame(['.dark', "[data-color-scheme='dark']"], $this->selectorsOfTheBlockDeclaring('color-scheme: dark;'));
    }

    /**
     * @return list<string>
     */
    private function selectorsOfTheBlockDeclaring(string $declaration): array
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->read('resources/css/app.css'));
        $position = strpos($css, $declaration);
        $this->assertNotFalse($position, "app.css declares {$declaration}");

        $before = substr($css, 0, $position);
        $open = (int) strrpos($before, '{');
        $start = max((int) strrpos(substr($before, 0, $open), '}'), (int) strrpos(substr($before, 0, $open), '{'));
        $selectors = substr($before, $start + 1, $open - $start - 1);

        return array_map(trim(...), explode(',', $selectors));
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3).'/'.$path);
    }
}
