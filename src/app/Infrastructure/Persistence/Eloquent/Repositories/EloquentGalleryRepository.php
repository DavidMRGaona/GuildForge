<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Entities\Gallery;
use App\Domain\Repositories\GalleryRepositoryInterface;
use App\Domain\ValueObjects\GalleryId;
use App\Domain\ValueObjects\Slug;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use DateTimeImmutable;
use Illuminate\Support\Collection;

final readonly class EloquentGalleryRepository implements GalleryRepositoryInterface
{
    public function findById(GalleryId $id): ?Gallery
    {
        $model = GalleryModel::query()->find($id->value);

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findBySlug(string $slug): ?Gallery
    {
        $model = GalleryModel::query()->where('slug', $slug)->first();

        if ($model === null) {
            return null;
        }

        return $this->toDomain($model);
    }

    public function findPublished(): Collection
    {
        return GalleryModel::query()
            ->where('is_published', true)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (GalleryModel $model): Gallery => $this->toDomain($model));
    }

    public function findAll(): Collection
    {
        return GalleryModel::query()
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn (GalleryModel $model): Gallery => $this->toDomain($model));
    }

    public function save(Gallery $gallery): void
    {
        GalleryModel::query()->updateOrCreate(
            ['id' => $gallery->id()->value],
            $this->toArray($gallery),
        );
    }

    public function delete(Gallery $gallery): void
    {
        GalleryModel::query()->where('id', $gallery->id()->value)->delete();
    }

    private function toDomain(GalleryModel $model): Gallery
    {
        return new Gallery(
            id: new GalleryId($model->id),
            title: $model->title,
            slug: new Slug($model->slug),
            description: $model->description,
            coverImagePublicId: $model->cover_image_public_id,
            isPublished: $model->is_published,
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
    private function toArray(Gallery $gallery): array
    {
        return [
            'id' => $gallery->id()->value,
            'title' => $gallery->title(),
            'slug' => $gallery->slug()->value,
            'description' => $gallery->description(),
            'cover_image_public_id' => $gallery->coverImagePublicId(),
            'is_published' => $gallery->isPublished(),
        ];
    }
}
