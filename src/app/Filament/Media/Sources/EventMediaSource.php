<?php

declare(strict_types=1);

namespace App\Filament\Media\Sources;

use App\Filament\Resources\EventResource;
use App\Infrastructure\Media\Sources\EloquentColumnMediaSource;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class EventMediaSource extends EloquentColumnMediaSource
{
    public function type(): string
    {
        return 'events';
    }

    public function label(): string
    {
        return __('filament.media_library.sources.events');
    }

    public function folders(): array
    {
        return ['events/'];
    }

    protected function modelClass(): string
    {
        return EventModel::class;
    }

    protected function column(): string
    {
        return 'image_public_id';
    }

    protected function role(): string
    {
        return __('filament.media_library.roles.image');
    }

    protected function titleOf(Model $record): string
    {
        return (string) $record->getAttribute('title');
    }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        if (! Gate::forUser($user)->allows('update', $record)) {
            return null;
        }

        return EventResource::getUrl('edit', ['record' => $record]);
    }
}
