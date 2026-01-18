<?php

declare(strict_types=1);

namespace App\Domain\Repositories;

use App\Domain\Entities\Gallery;
use App\Domain\ValueObjects\GalleryId;
use Illuminate\Support\Collection;

interface GalleryRepositoryInterface
{
    public function findById(GalleryId $id): ?Gallery;

    public function findBySlug(string $slug): ?Gallery;

    /**
     * @return Collection<int, Gallery>
     */
    public function findPublished(): Collection;

    /**
     * @return Collection<int, Gallery>
     */
    public function findAll(): Collection;

    public function save(Gallery $gallery): void;

    public function delete(Gallery $gallery): void;
}
