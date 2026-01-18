<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Photo;
use App\Domain\ValueObjects\GalleryId;
use App\Domain\ValueObjects\PhotoId;
use Illuminate\Support\Collection;

interface PhotoRepositoryInterface
{
    public function findById(PhotoId $id): ?Photo;

    /**
     * @return Collection<int, Photo>
     */
    public function findByGalleryId(GalleryId $galleryId): Collection;

    public function save(Photo $photo): void;

    public function delete(Photo $photo): void;

    public function deleteByGalleryId(GalleryId $galleryId): void;
}
