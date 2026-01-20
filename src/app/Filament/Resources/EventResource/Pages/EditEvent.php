<?php

declare(strict_types=1);

namespace App\Filament\Resources\EventResource\Pages;

use App\Filament\Resources\EventResource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

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

        /** @var EventModel $record */
        $record = $this->record;
        $record->tags()->sync($tagIds);
    }
}
