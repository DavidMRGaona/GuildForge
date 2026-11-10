<?php

declare(strict_types=1);

namespace App\Infrastructure\Media\Sources;

use App\Filament\Resources\GalleryResource;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

/**
 * Photos of galleries: the record is the photo, the title and the edit page are the gallery's.
 */
final class GalleryPhotoMediaSource extends EloquentColumnMediaSource
{
    public function type(): string
    {
        return 'galleries';
    }

    public function label(): string
    {
        return __('filament.media_library.sources.galleries');
    }

    public function folders(): array
    {
        return ['galleries/'];
    }

    protected function modelClass(): string
    {
        return PhotoModel::class;
    }

    protected function column(): string
    {
        return 'image_public_id';
    }

    protected function role(): string
    {
        return __('filament.media_library.roles.photo');
    }

    protected function query(): Builder
    {
        return parent::query()->with('gallery');
    }

    protected function titleOf(Model $record): string
    {
        $gallery = $this->galleryOf($record);

        return $gallery instanceof GalleryModel ? $gallery->title : '';
    }

    protected function editUrlOf(Model $record, object $user): ?string
    {
        $gallery = $this->galleryOf($record);

        if (! $gallery instanceof GalleryModel || ! Gate::forUser($user)->allows('update', $gallery)) {
            return null;
        }

        return GalleryResource::getUrl('edit', ['record' => $gallery]);
    }

    private function galleryOf(Model $record): ?GalleryModel
    {
        $gallery = $record instanceof PhotoModel ? $record->gallery : null;

        return $gallery instanceof GalleryModel ? $gallery : null;
    }
}
