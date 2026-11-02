<?php

declare(strict_types=1);

namespace App\Filament\Resources\GalleryResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Resources\GalleryResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGalleries extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = GalleryResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ...array_filter([$this->knowledgeGuideAction('core.panel.manage-galleries')]),
        ];
    }
}
