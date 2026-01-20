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
            ->with([
                'tags',
                'photos' => function ($query) use ($photoLimit): void {
                    $query->orderBy('sort_order')->limit($photoLimit);
                },
            ])
            ->where('is_published', true)
            ->orderByDesc('is_featured')
            ->orderByDesc('updated_at')
            ->first();

        return $gallery ? $this->dtoFactory->createGalleryDetailDTO($gallery) : null;
    }

    public function getPublishedPaginated(int $page = 1, int $perPage = 12, ?array $tagSlugs = null): array
    {
        $offset = ($page - 1) * $perPage;

        $query = GalleryModel::query()
            ->with('tags')
            ->withCount('photos')
            ->where('is_published', true);

        if ($tagSlugs !== null && count($tagSlugs) > 0) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('slug', $tagSlugs));
        }

        $galleries = $query
            ->orderBy('created_at', 'desc')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return $galleries->map(fn (GalleryModel $gallery) => $this->dtoFactory->createGalleryDTO($gallery))->all();
    }

    public function getPublishedTotal(?array $tagSlugs = null): int
    {
        $query = GalleryModel::query()
            ->where('is_published', true);

        if ($tagSlugs !== null && count($tagSlugs) > 0) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('slug', $tagSlugs));
        }

        return $query->count();
    }

    public function findPublishedBySlug(string $slug): ?GalleryDetailResponseDTO
    {
        $gallery = GalleryModel::query()
            ->with(['tags', 'photos'])
            ->where('slug', $slug)
            ->where('is_published', true)
            ->first();

        return $gallery ? $this->dtoFactory->createGalleryDetailDTO($gallery) : null;
    }
}
