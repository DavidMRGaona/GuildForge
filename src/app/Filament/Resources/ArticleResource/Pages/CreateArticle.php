<?php

declare(strict_types=1);

namespace App\Filament\Resources\ArticleResource\Pages;

use App\Filament\Resources\ArticleResource;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use Filament\Resources\Pages\CreateRecord;

class CreateArticle extends CreateRecord
{
    protected static string $resource = ArticleResource::class;

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

        /** @var ArticleModel $record */
        $record = $this->record;
        $record->tags()->sync($tagIds);
    }
}
