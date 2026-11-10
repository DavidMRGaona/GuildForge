<?php

declare(strict_types=1);

namespace App\Filament\Media\Sources;

use App\Filament\Resources\HeroSlideResource;
use App\Infrastructure\Media\Sources\EloquentColumnMediaSource;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

final class HeroSlideMediaSource extends EloquentColumnMediaSource
{
    public function type(): string
    {
        return 'hero_slides';
    }

    public function label(): string
    {
        return __('filament.media_library.sources.hero_slides');
    }

    public function folders(): array
    {
        return ['hero-slides/'];
    }

    protected function modelClass(): string
    {
        return HeroSlideModel::class;
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

        return HeroSlideResource::getUrl('edit', ['record' => $record]);
    }
}
