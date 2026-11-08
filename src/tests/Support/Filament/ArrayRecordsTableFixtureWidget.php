<?php

declare(strict_types=1);

namespace Tests\Support\Filament;

use App\Filament\Actions\RowDetailsAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * A table whose rows come from `->records()` as plain arrays, as a module widget that mixes
 * several sources does it (pending moderation in game-tables): row details must read them too.
 */
final class ArrayRecordsTableFixtureWidget extends TableWidget
{
    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => [
                'first' => ['id' => 'first', 'title' => 'Mesa del sábado', 'kind' => 'Mesa', 'pending' => 3],
            ])
            ->columns([
                TextColumn::make('title')->label('Título'),
                TextColumn::make('kind')->label('Tipo')->visibleFrom('md'),
                TextColumn::make('pending')->label('Pendientes')->visibleFrom('xl'),
            ])
            ->recordActions([
                RowDetailsAction::makeIconButton(),
            ]);
    }
}
