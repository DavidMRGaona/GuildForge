<?php

declare(strict_types=1);

namespace App\Filament\Resources\KnowledgeGuideResource\Pages;

use App\Application\Knowledge\Services\SiteGuideServiceInterface;
use App\Domain\Knowledge\Exceptions\SiteGuideException;
use App\Domain\Knowledge\ValueObjects\GuideId;
use App\Filament\Resources\KnowledgeGuideResource;
use App\Filament\Resources\KnowledgeGuideResource\SiteGuideFormData;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Checkbox;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

/**
 * @property KnowledgeGuideModel $record
 */
class EditKnowledgeGuide extends EditRecord
{
    protected static string $resource = KnowledgeGuideResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->schema([
                    Checkbox::make('confirmation')
                        ->label(__('filament.knowledge_guides.actions.delete_confirmation_check'))
                        ->accepted()
                        ->required(),
                ])
                ->using(function (KnowledgeGuideModel $record): bool {
                    resolve(SiteGuideServiceInterface::class)->delete(GuideId::fromString($record->guide_id));

                    return true;
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return SiteGuideFormData::fromModel($this->record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof KnowledgeGuideModel);

        try {
            resolve(SiteGuideServiceInterface::class)->update(GuideId::fromString($record->guide_id), SiteGuideFormData::toDto($data));
        } catch (SiteGuideException $e) {
            report($e);
            Notification::make()
                ->title(__('filament.knowledge_guides.notifications.not_saved'))
                ->danger()
                ->send();

            // The form stays open with what was typed
            throw new Halt($e->getMessage(), $e->getCode(), $e);
        }

        return $record->refresh();
    }
}
