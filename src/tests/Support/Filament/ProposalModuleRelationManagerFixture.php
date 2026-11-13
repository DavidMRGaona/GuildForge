<?php

declare(strict_types=1);

namespace Tests\Support\Filament;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * A relation manager a module would add to the event form with EventResource::extendRelations().
 */
final class ProposalModuleRelationManagerFixture extends RelationManager
{
    protected static string $relationship = 'tags';

    public function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')]);
    }
}
