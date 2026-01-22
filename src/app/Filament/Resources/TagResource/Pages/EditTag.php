<?php

declare(strict_types=1);

namespace App\Filament\Resources\TagResource\Pages;

use App\Application\Services\TagQueryServiceInterface;
use App\Filament\Resources\TagResource;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/**
 * @property TagModel $record
 */
class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    /**
     * @return array<DeleteAction>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->hidden(fn (): bool => !$this->getTagQueryService()->canDelete($this->record->id)),
        ];
    }

    private function getTagQueryService(): TagQueryServiceInterface
    {
        return app(TagQueryServiceInterface::class);
    }
}
