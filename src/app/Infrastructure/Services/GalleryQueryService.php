<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\Response\GalleryDetailResponseDTO;
use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\GalleryQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;

final readonly class GalleryQueryService implements GalleryQueryServiceInterface
{
    public function __construct(
        private ResponseDTOFactoryInterface $dtoFactory,
    ) {
    }

    public function getFeaturedGallery(int $photoLimit = 12): ?GalleryDetailResponseDTO
    {
        $gallery = GalleryModel::query()
            ->with(['photos' => function ($query) use ($photoLimit): void {
                $query->orderBy('sort_order')->limit($photoLimit);
            }])
            ->where('is_published', true)
            ->orderByDesc('is_featured')
            ->orderByDesc('updated_at')
            ->first();

        return $gallery ? $this->dtoFactory->createGalleryDetailDTO($gallery) : null;
    }

    public function getPublishedPaginated(int $page = 1, int $perPage = 12): array
    {
        $offset = ($page - 1) * $perPage;

        $galleries = GalleryModel::query()
            ->withCount('photos')
            ->where('is_published', true)
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return $galleries->map(fn (GalleryModel $gallery) => $this->dtoFactory->createGalleryDTO($gallery))->all();
    }

    public function getPublishedTotal(): int
    {
        return GalleryModel::query()
            ->where('is_published', true)
            ->count();
    }

    public function findPublishedBySlug(string $slug): ?GalleryDetailResponseDTO
    {
        $gallery = GalleryModel::query()
            ->with('photos')
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        return $gallery ? $this->dtoFactory->createGalleryDetailDTO($gallery) : null;
    }
}
