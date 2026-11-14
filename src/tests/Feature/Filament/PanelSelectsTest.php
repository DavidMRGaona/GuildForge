<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Support\PanelSelects;
use Filament\Forms\Components\Select;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Filters\SelectFilter;
use Tests\TestCase;

/**
 * No dropdown of the panel opens the browser's own list, and long fixed lists can be searched
 * (core#648).
 */
final class PanelSelectsTest extends TestCase
{
    public function test_selects_filters_and_columns_are_drawn_by_filament(): void
    {
        $this->assertFalse(Select::make('status')->isNative());
        $this->assertFalse(SelectFilter::make('status')->isNative());
        $this->assertFalse(SelectColumn::make('status')->isNative());
    }

    public function test_a_field_may_still_ask_for_the_native_list(): void
    {
        $this->assertTrue(Select::make('status')->native()->isNative());
        $this->assertTrue(SelectFilter::make('status')->native()->isNative());
    }

    public function test_a_long_fixed_list_gets_a_search_box(): void
    {
        $this->assertTrue(Select::make('size')->options($this->fixedOptions(PanelSelects::SEARCH_THRESHOLD))->isSearchable());
    }

    public function test_a_short_fixed_list_does_not(): void
    {
        $this->assertFalse(Select::make('size')->options(['a' => 'A', 'b' => 'B'])->isSearchable());
    }

    public function test_a_multiple_select_stays_searchable(): void
    {
        $this->assertTrue(Select::make('tags')->options(['a' => 'A'])->multiple()->isSearchable());
    }

    public function test_what_a_field_chooses_wins(): void
    {
        $this->assertFalse(Select::make('size')->options($this->fixedOptions(20))->searchable(false)->isSearchable());
        $this->assertTrue(Select::make('size')->options(['a' => 'A'])->searchable()->isSearchable());
    }

    public function test_counting_options_that_ask_whether_the_select_searches_does_not_loop(): void
    {
        $select = Select::make('size')->options(static fn (Select $component): array => $component->isSearchable() ? [] : ['a' => 'A']);

        $this->assertFalse($select->isSearchable());
    }

    /**
     * @return array<string, string>
     */
    private function fixedOptions(int $count): array
    {
        $options = [];
        foreach (range(1, $count) as $number) {
            $options["option-{$number}"] = "Opción {$number}";
        }

        return $options;
    }
}
