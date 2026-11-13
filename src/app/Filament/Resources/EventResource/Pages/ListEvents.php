<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Domain\Proposals\Enums\ProposalType;
use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Proposals\ProposalReviewPanel;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;

class ListEvents extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected static string $resource = EventResource::class;

    /**
     * «Todos» and «En revisión» with its count (core#575).
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return ProposalReviewPanel::tabs(ProposalType::Event, EventModel::class);
    }

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
