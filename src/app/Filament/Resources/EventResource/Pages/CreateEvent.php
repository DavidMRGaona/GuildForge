<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Application\Search\Services\SearchIndexerInterface;
use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
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
