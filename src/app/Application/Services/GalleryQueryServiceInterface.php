<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTOs\Response\GalleryDetailResponseDTO;
use App\Application\DTOs\Response\GalleryResponseDTO;

interface GalleryQueryServiceInterface
{
    public function getFeaturedGallery(int $photoLimit = 12): ?GalleryDetailResponseDTO;

    /**
     * @return array<int, GalleryResponseDTO>
     */
    public function getPublishedPaginated(int $page = 1, int $perPage = 12): array;

    public function getPublishedTotal(): int;

    public function findPublishedBySlug(string $slug): ?GalleryDetailResponseDTO;
}
