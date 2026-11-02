<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Resources\EventResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEvents extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = EventResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ...array_filter([$this->knowledgeGuideAction('core.panel.manage-events')]),
        ];
    }
}
