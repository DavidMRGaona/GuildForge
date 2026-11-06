<?php

declare(strict_types=1);

namespace App\Filament\Resources\KnowledgeGuideResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Resources\KnowledgeGuideResource;
use App\Filament\Resources\KnowledgeGuideResource\Widgets\KnowledgeSearchMissesWidget;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKnowledgeGuides extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = KnowledgeGuideResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ...array_filter([$this->knowledgeGuideAction('core.panel.help-guides')]),
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [KnowledgeSearchMissesWidget::class];
    }
}
