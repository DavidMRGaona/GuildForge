<?php

declare(strict_types=1);

namespace App\Filament\Resources\HeroSlideResource\Pages;

use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Resources\HeroSlideResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHeroSlides extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = HeroSlideResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ...array_filter([$this->knowledgeGuideAction('core.panel.hero-slides')]),
        ];
    }
}
