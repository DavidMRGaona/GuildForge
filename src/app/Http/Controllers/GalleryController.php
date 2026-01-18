<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use Inertia\Inertia;
use Inertia\Response;

final class GalleryController extends Controller
{
    public function index(): Response
    {
        $galleries = GalleryModel::query()
            ->where('is_published', true)
            ->withCount('photos')
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        return Inertia::render('Gallery/Index', [
            'galleries' => [
                'data' => $galleries->map(fn (GalleryModel $gallery): array => $this->mapGalleryModel($gallery)),
                'meta' => [
                    'currentPage' => $galleries->currentPage(),
                    'lastPage' => $galleries->lastPage(),
                    'perPage' => $galleries->perPage(),
                    'total' => $galleries->total(),
                ],
                'links' => [
                    'first' => $galleries->url(1),
                    'last' => $galleries->url($galleries->lastPage()),
                    'prev' => $galleries->previousPageUrl(),
                    'next' => $galleries->nextPageUrl(),
                ],
            ],
        ]);
    }

    public function show(string $slug): Response
    {
        $gallery = GalleryModel::query()
            ->where('slug', $slug)
            ->where('is_published', true)
            ->with('photos')
            ->first();

        if ($gallery === null) {
            abort(404);
        }

        return Inertia::render('Gallery/Show', [
            'gallery' => $this->mapGalleryModelWithPhotos($gallery),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function mapGalleryModel(GalleryModel $gallery): array
    {
        return [
            'id' => $gallery->id,
            'title' => $gallery->title,
            'slug' => $gallery->slug,
            'description' => $gallery->description,
            'coverImagePublicId' => $gallery->cover_image_public_id,
            'isPublished' => $gallery->is_published,
            'photoCount' => $gallery->photos_count ?? 0,
            'createdAt' => $gallery->created_at?->format('c'),
            'updatedAt' => $gallery->updated_at?->format('c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapGalleryModelWithPhotos(GalleryModel $gallery): array
    {
        return [
            'id' => $gallery->id,
            'title' => $gallery->title,
            'slug' => $gallery->slug,
            'description' => $gallery->description,
            'coverImagePublicId' => $gallery->cover_image_public_id,
            'isPublished' => $gallery->is_published,
            'photos' => $gallery->photos->map(fn (PhotoModel $photo): array => $this->mapPhoto($photo))->toArray(),
            'createdAt' => $gallery->created_at?->format('c'),
            'updatedAt' => $gallery->updated_at?->format('c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapPhoto(PhotoModel $photo): array
    {
        return [
            'id' => $photo->id,
            'imagePublicId' => $photo->image_public_id,
            'caption' => $photo->caption,
            'sortOrder' => $photo->sort_order,
        ];
    }
}
