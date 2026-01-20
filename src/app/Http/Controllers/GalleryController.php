<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Factories\ResponseDTOFactoryInterface;
use App\Application\Services\GalleryQueryServiceInterface;
use App\Http\Concerns\BuildsPaginatedResponse;
use App\Http\Resources\GalleryResource;
use App\Http\Resources\GalleryWithPhotosResource;
use App\Http\Resources\TagResource;
use App\Infrastructure\Persistence\Eloquent\Models\TagModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GalleryController extends Controller
{
    use BuildsPaginatedResponse;

    private const PER_PAGE = 12;

    public function __construct(
        private readonly GalleryQueryServiceInterface $galleryQuery,
        private readonly ResponseDTOFactoryInterface $dtoFactory,
    ) {
    }

    public function index(Request $request): Response
    {
        $page = $this->getCurrentPage();
        $tagsParam = $request->query('tags');
        $tagSlugs = null;
        if (is_string($tagsParam) && $tagsParam !== '') {
            $tagSlugs = array_filter(explode(',', $tagsParam));
        }

        $galleries = $this->galleryQuery->getPublishedPaginated($page, self::PER_PAGE, $tagSlugs);
        $total = $this->galleryQuery->getPublishedTotal($tagSlugs);

        $availableTags = TagModel::query()
            ->forType('galleries')
            ->ordered()
            ->get()
            ->map(fn (TagModel $tag) => $this->dtoFactory->createTagDTO($tag))
            ->all();

        return Inertia::render('Gallery/Index', [
            'galleries' => $this->buildPaginatedResponse(
                items: $galleries,
                total: $total,
                page: $page,
                perPage: self::PER_PAGE,
                resourceClass: GalleryResource::class,
            ),
            'tags' => TagResource::collection($availableTags)->resolve(),
            'currentTags' => $tagSlugs ?? [],
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
