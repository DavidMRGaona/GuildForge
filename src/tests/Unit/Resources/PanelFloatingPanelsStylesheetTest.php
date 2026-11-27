<?php

declare(strict_types=1);

namespace Tests\Unit\Resources;

use PHPUnit\Framework\TestCase;

/**
 * The calendar of a date field and the list of a select open upwards when there is no room below
 * the field (often on a phone). They are drawn at z-index 20, under the sticky top bar (z-index 30),
 * which then hid the month, the year and the weekdays of the calendar: there was no way to change
 * the month (core#856). The panel theme draws them over the top bar.
 */
final class PanelFloatingPanelsStylesheetTest extends TestCase
{
    /** Filament's `.fi-topbar-ctn` (sticky). */
    private const int TOP_BAR_Z_INDEX = 30;

    public function test_the_calendar_of_a_date_field_is_drawn_over_the_top_bar(): void
    {
        $this->assertGreaterThan(self::TOP_BAR_Z_INDEX, $this->zIndexOf('.fi-fo-date-time-picker-panel'));
    }

    public function test_the_list_of_a_select_is_drawn_over_the_top_bar(): void
    {
        $this->assertGreaterThan(self::TOP_BAR_Z_INDEX, $this->zIndexOf('.fi-select-input .fi-dropdown-panel'));
    }

    private function zIndexOf(string $selector): int
    {
        $css = (string) preg_replace('#/\*.*?\*/#s', '', $this->read('resources/css/filament/admin/theme.css'));
        $found = preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $blocks, PREG_SET_ORDER);
        $this->assertNotFalse($found);

        foreach ($blocks as $block) {
            $selectors = array_map(trim(...), explode(',', $block[1]));
            if (in_array($selector, $selectors, true) && preg_match('/z-index:\s*(\d+)\s*;/', $block[2], $zIndex) === 1) {
                return (int) $zIndex[1];
            }
        }

        $this->fail("The panel theme gives {$selector} no z-index");
    }

    private function read(string $path): string
    {
        return (string) file_get_contents(dirname(__DIR__, 3).'/'.$path);
    }
}
