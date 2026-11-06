<?php

declare(strict_types=1);

namespace App\Filament\Resources\KnowledgeGuideResource\Pages;

use App\Application\Knowledge\Services\SiteGuideServiceInterface;
use App\Domain\Knowledge\Exceptions\SiteGuideException;
use App\Filament\Resources\KnowledgeGuideResource;
use App\Filament\Resources\KnowledgeGuideResource\SiteGuideFormData;
use App\Infrastructure\Knowledge\Persistence\Eloquent\Models\KnowledgeGuideModel;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class CreateKnowledgeGuide extends CreateRecord
{
    protected static string $resource = KnowledgeGuideResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        try {
            $id = resolve(SiteGuideServiceInterface::class)->create(SiteGuideFormData::toDto($data));

            return KnowledgeGuideModel::query()
                ->where('guide_id', $id->value())
                ->where('locale', KnowledgeGuideResource::locale())
                ->firstOrFail();
        } catch (SiteGuideException $e) {
            report($e);
            Notification::make()
                ->title(__('filament.knowledge_guides.notifications.not_saved'))
                ->danger()
                ->send();

            // The form stays open with what was typed
            throw new Halt($e->getMessage(), $e->getCode(), $e);
        }
    }
}
