<?php

declare(strict_types=1);

namespace App\Filament\Resources\GalleryResource\Pages;

use App\Application\Search\Services\SearchIndexerInterface;
use App\Filament\Resources\GalleryResource;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGallery extends EditRecord
{
    /**
     * The whole save runs in one transaction: images a record lets go of are released after the commit, once
     * every record of the save is written (a later one may have chosen the same file from the library).
     */
    protected ?bool $hasDatabaseTransactions = true;

    protected static string $resource = GalleryResource::class;

    /**
     * @return array<DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
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

        /** @var GalleryModel $record */
        $record = $this->record;
        $record->tags()->sync($tagIds);
        // Tags are saved after the record, so the refresh queued by saving it may have missed them
        app(SearchIndexerInterface::class)->refresh('galleries', (string) $record->getKey());
    }
}
