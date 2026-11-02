<?php

declare(strict_types=1);

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Resources\ArticleResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListArticles extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = ArticleResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ...array_filter([$this->knowledgeGuideAction('core.panel.manage-articles')]),
        ];
    }
}
