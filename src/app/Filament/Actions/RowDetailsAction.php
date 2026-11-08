<?php

declare(strict_types=1);

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Enums\Alignment;
use Filament\Tables\Columns\Column;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * «Ver detalles» (core#399): a slide-over with every column of the row, drawn as the table draws
 * it. Tables hide secondary columns on narrow screens (core#383); this is how they stay readable,
 * also for records without an edit page. It reads the table's own columns, so a column added to
 * the table shows here too, and it hides itself from the width where every column already shows.
 */
final class RowDetailsAction
{
    public const string NAME = 'rowDetails';

    /**
     * Breakpoints from narrowest to widest, each with the literal class that hides the action
     * from it (written out so the panel theme compiles them).
     */
    private const array HIDDEN_FROM = [
        'sm' => 'sm:hidden',
        'md' => 'md:hidden',
        'lg' => 'lg:hidden',
        'xl' => 'xl:hidden',
        '2xl' => '2xl:hidden',
    ];

    public static function make(): Action
    {
        return Action::make(self::NAME)
            ->label(__('filament.row_details.action'))
            ->icon('heroicon-o-eye')
            ->color('gray')
            ->slideOver()
            // Not the record title: without a title attribute Filament gives the model label, and the
            // main column (first entry) already names the record
            ->modalHeading(__('filament.row_details.heading'))
            ->modalContent(fn (Action $action, Model|array $record): View => view('filament.tables.row-details', [
                'entries' => self::entries(self::tableOf($action), $record),
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('filament.row_details.close'))
            ->extraAttributes(fn (Action $action): array => array_filter([
                'class' => self::hiddenFromClass(self::tableOf($action)),
            ]));
    }

    /**
     * For tables with no other row action: one eye icon instead of a menu with a single entry.
     */
    public static function makeIconButton(): Action
    {
        return self::make()
            ->iconButton()
            ->tooltip(__('filament.row_details.action'));
    }

    /**
     * Every column shown for the record, in table order, with its label and its cell. Rows that
     * come from `->records()` are arrays (core#403); Filament's columns read both.
     *
     * @param  Model|array<string, mixed>  $record
     * @return list<array{name: string, label: string, html: HtmlString, empty: bool}>
     */
    public static function entries(Table $table, Model|array $record): array
    {
        $entries = [];

        foreach ($table->getColumns() as $column) {
            $column->record($record);

            if ($column->isHidden()) {
                continue;
            }

            $html = self::cell($column);
            $entries[] = [
                'name' => $column->getName(),
                'label' => self::label($column),
                'html' => $html,
                // A blank cell (no image, no location…) reads as a loading error in the panel
                'empty' => trim(strip_tags($html->toHtml(), '<img><svg>')) === '',
            ];
        }

        return $entries;
    }

    /**
     * The class that hides the action from the widest breakpoint any column waits for; null when
     * some column is only shown on narrow screens or none is ever hidden (nothing to hide from).
     */
    public static function hiddenFromClass(Table $table): ?string
    {
        $widest = -1;
        $breakpoints = array_keys(self::HIDDEN_FROM);

        foreach ($table->getColumns() as $column) {
            if ($column->getHiddenFrom() !== null) {
                return null;
            }

            $index = array_search($column->getVisibleFrom(), $breakpoints, true);
            if (is_int($index) && $index > $widest) {
                $widest = $index;
            }
        }

        return $widest >= 0 ? self::HIDDEN_FROM[$breakpoints[$widest]] : null;
    }

    /**
     * The column's cell aligned at the start of the panel. The column object is the table's own
     * and Livewire renders the table again in the same response, so its alignment is put back.
     */
    private static function cell(Column $column): HtmlString
    {
        $alignment = $column->getAlignment();

        try {
            $column->alignment(Alignment::Start);

            // Columns with their own Blade view (ViewColumn) have no embedded HTML
            return new HtmlString($column instanceof HasEmbeddedView ? $column->toEmbeddedHtml() : $column->toHtml());
        } finally {
            $column->alignment($alignment);
        }
    }

    private static function label(Column $column): string
    {
        $label = $column->getLabel();

        return $label instanceof Htmlable ? strip_tags($label->toHtml()) : $label;
    }

    private static function tableOf(Action $action): Table
    {
        $table = $action->getTable();
        if (! $table instanceof Table) {
            throw new \LogicException('RowDetailsAction only works as a table row action.');
        }

        return $table;
    }
}
