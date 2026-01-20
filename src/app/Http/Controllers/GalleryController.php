<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\GalleryQueryServiceInterface;
use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Resources\GalleryResource;
use App\Http\Resources\GalleryWithPhotosResource;
use Inertia\Inertia;
use Inertia\Response;

final class GalleryController extends Controller
{
    use BuildsPaginatedResponse;

    private const PER_PAGE = 12;

    public function __construct(
        private readonly GalleryQueryServiceInterface $galleryQuery,
    ) {
    }

    public function index(): Response
    {
        $page = $this->getCurrentPage();

        $galleries = $this->galleryQuery->getPublishedPaginated($page, self::PER_PAGE);
        $total = $this->galleryQuery->getPublishedTotal();

        return Inertia::render('Gallery/Index', [
            'galleries' => $this->buildPaginatedResponse(
                items: $galleries,
                total: $total,
                page: $page,
                perPage: self::PER_PAGE,
                resourceClass: GalleryResource::class,
            ),
        ]);
    }

    public function show(string $slug): Response
    {
        $gallery = $this->galleryQuery->findPublishedBySlug($slug);

        if ($gallery === null) {
            abort(404);
        }

        return Inertia::render('Gallery/Show', [
            'gallery' => GalleryWithPhotosResource::make($gallery)->resolve(),
        ]);
    }
}
