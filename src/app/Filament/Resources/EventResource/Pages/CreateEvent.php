<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Application\Search\Services\SearchIndexerInterface;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    /**
     * The whole save runs in one transaction: images a record lets go of are released after the commit, once
     * every record of the save is written (a later one may have chosen the same file from the library).
     */
    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = EventResource::class;

    protected function afterCreate(): void
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
