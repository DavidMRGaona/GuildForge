<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Application\Search\Services\SearchIndexerInterface;
use App\Domain\Proposals\Enums\ProposalType;
use App\Filament\Concerns\HasKnowledgeGuideAction;
use App\Filament\Proposals\ProposalReviewActions;
use App\Filament\Proposals\ProposalReviewPanel;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    /**
     * The whole save runs in one transaction: images a record lets go of are released after the commit, once
     * every record of the save is written (a later one may have chosen the same file from the library).
     */
    protected ?bool $hasDatabaseTransactions = true;

    use HasKnowledgeGuideAction;

    protected static string $resource = EventResource::class;

    /**
     * Display relation managers in tabs alongside the form content.
     * This puts the form in a "Detalles" tab and each RelationManager in its own tab.
     */
    public function hasCombinedRelationManagerTabsWithContent(): bool
    {
        return true;
    }

    /**
     * Label for the main content tab when using combined tabs.
     */
    public function getContentTabLabel(): ?string
    {
        return __('Detalles');
    }

    /**
     * The tabs modules add (extendRelations()) wait until the event is approved, like their form
     * sections: while it is a proposal, its module requests are the only way to configure them
     * (core#575).
     *
     * @return array<mixed>
     */
    public function getRelationManagers(): array
    {
        $managers = parent::getRelationManagers();

        if (! ProposalReviewPanel::isUnapproved($this->getRecord())) {
            return $managers;
        }

        $fromModules = EventResource::getExtendedRelations();

        return array_filter($managers, static fn (mixed $manager): bool => ! in_array($manager, $fromModules, true));
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ...ProposalReviewActions::for(ProposalType::Event),
            DeleteAction::make(),
            ...array_filter([ProposalReviewActions::isProposal($this->getRecord()) ? $this->knowledgeGuideAction(ProposalReviewActions::GUIDE) : null]),
        ];
    }

    protected function afterSave(): void
    {
        $this->syncTags();
    }

    private function syncTags(): void
    {
        $categoryId = $this->data['category_id'] ?? null;
        $additionalTagIds = $this->data['additional_tag_ids'] ?? [];

        $tagIds = array_filter(array_merge(
            $categoryId !== null ? [$categoryId] : [],
            $additionalTagIds
        ));

        /** @var EventModel $record */
        $record = $this->record;
        $record->tags()->sync($tagIds);
        // Tags are saved after the record, so the refresh queued by saving it may have missed them
        app(SearchIndexerInterface::class)->refresh('events', (string) $record->getKey());
    }
}
