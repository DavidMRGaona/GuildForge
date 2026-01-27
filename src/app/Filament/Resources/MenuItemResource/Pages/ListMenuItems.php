<?php

declare(strict_types=1);

namespace App\Filament\Resources\MenuItemResource\Pages;

use App\Domain\Navigation\Enums\MenuLocation;
use App\Filament\Resources\MenuItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListMenuItems extends ListRecords
{
    protected static string $resource = MenuItemResource::class;

    /**
     * @return array<CreateAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'header' => Tab::make(__('filament.menu_items.locations.header'))
                ->icon('heroicon-o-window')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('menu_items.location', MenuLocation::Header->value)),

            'footer' => Tab::make(__('filament.menu_items.locations.footer'))
                ->icon('heroicon-o-bars-3-bottom-left')
                ->modifyQueryUsing(fn (Builder $query) => $query->where('menu_items.location', MenuLocation::Footer->value)),
        ];
    }
}
