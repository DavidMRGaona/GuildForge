<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Entities\Photo;
use App\Domain\Repositories\PhotoRepositoryInterface;
use App\Domain\ValueObjects\GalleryId;
use App\Domain\ValueObjects\PhotoId;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final readonly class EloquentPhotoRepository implements PhotoRepositoryInterface
{
    public function findById(PhotoId $id): ?Photo
    {
        $model = PhotoModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findByGalleryId(GalleryId $galleryId): Collection
    {
        return PhotoModel::query()
            ->where('gallery_id', $galleryId->value)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PhotoModel $model): Photo => $this->toDomain($model));
    }

    public function save(Photo $photo): void
    {
        PhotoModel::query()->updateOrCreate(
            ['id' => $photo->id()->value],
            $this->toArray($photo),
        );
    }

    public function delete(Photo $photo): void
    {
        PhotoModel::query()->where('id', $photo->id()->value)->delete();
    }

    public function deleteByGalleryId(GalleryId $galleryId): void
    {
        PhotoModel::query()->where('gallery_id', $galleryId->value)->delete();
    }

    private function toDomain(PhotoModel $model): Photo
    {
        return new Photo(
            id: new PhotoId($model->id),
            galleryId: new GalleryId($model->gallery_id),
            imagePublicId: $model->image_public_id,
            caption: $model->caption,
            sortOrder: $model->sort_order,
            createdAt: $model->created_at !== null
                ? new DateTimeImmutable($model->created_at->toDateTimeString())
                : null,
            updatedAt: $model->updated_at !== null
                ? new DateTimeImmutable($model->updated_at->toDateTimeString())
                : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(Photo $photo): array
    {
        return [
            'id' => $photo->id()->value,
            'gallery_id' => $photo->galleryId()->value,
            'image_public_id' => $photo->imagePublicId(),
            'caption' => $photo->caption(),
            'sort_order' => $photo->sortOrder(),
        ];
    }
}
