<?php

declare(strict_types=1);

namespace App\Filament\Support;

use Filament\Forms\Components\Select;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Filters\SelectFilter;
use WeakMap;

/**
 * Dropdowns of the panel never open the browser's own list (core#648): selects, select filters
 * and select columns of the core and every module are drawn by Filament unless a field asks for
 * `native()` itself. A select with fixed options gets a search box from SEARCH_THRESHOLD options,
 * as the site's FormSelect does; a multiple select keeps Filament's default (searchable), and one
 * fed by a relationship or a search callback keeps what it configures.
 *
 * Filament's own records-per-page select is a view inside the package and stays native.
 */
final class PanelSelects
{
    public const int SEARCH_THRESHOLD = 8;

    /** @var WeakMap<Select, true>|null selects whose options are being counted */
    private static ?WeakMap $counting = null;

    public static function apply(): void
    {
        Select::configureUsing(static fn (Select $select): Select => $select
            ->native(false)
            ->searchable(static fn (Select $component): bool => self::wantsSearch($component)));
        SelectFilter::configureUsing(static fn (SelectFilter $filter): SelectFilter => $filter->native(false));
        SelectColumn::configureUsing(static fn (SelectColumn $column): SelectColumn => $column->native(false));
    }

    /**
     * Whether a select that did not choose gets a search box: a multiple one (Filament's own
     * default) or one with SEARCH_THRESHOLD fixed options or more.
     */
    public static function wantsSearch(Select $select): bool
    {
        if ($select->isMultiple()) {
            return true;
        }

        if ($select->hasRelationship() || $select->hasDynamicSearchResults()) {
            return false;
        }

        // Counting the options may ask whether the select is searchable again: answer no meanwhile
        self::$counting ??= new WeakMap;
        if (isset(self::$counting[$select])) {
            return false;
        }

        self::$counting[$select] = true;

        try {
            return count($select->getOptions()) >= self::SEARCH_THRESHOLD;
        } finally {
            unset(self::$counting[$select]);
        }
    }
}
