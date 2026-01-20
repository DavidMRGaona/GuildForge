<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Application\Services\ArticleQueryServiceInterface;
use App\Application\Services\EventQueryServiceInterface;
use App\Application\Services\GalleryQueryServiceInterface;
use App\Application\Services\HeroSlideQueryServiceInterface;
use App\Http\Resources\ArticleResource;
use App\Http\Resources\EventResource;
use App\Http\Resources\GalleryWithPhotosResource;
use App\Http\Resources\HeroSlideResource;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends Controller
{
    public function __construct(
        private readonly EventQueryServiceInterface $eventQuery,
        private readonly ArticleQueryServiceInterface $articleQuery,
        private readonly GalleryQueryServiceInterface $galleryQuery,
        private readonly HeroSlideQueryServiceInterface $heroSlideQuery,
    ) {
    }

    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'heroSlides' => HeroSlideResource::collection($this->heroSlideQuery->getActiveSlides())->resolve(),
            'upcomingEvents' => EventResource::collection($this->eventQuery->getUpcomingEvents(3))->resolve(),
            'latestArticles' => ArticleResource::collection($this->articleQuery->getLatestPublished(3))->resolve(),
            'featuredGallery' => $this->getFeaturedGallery(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getFeaturedGallery(): ?array
    {
        $gallery = $this->galleryQuery->getFeaturedGallery(6);

        return $gallery !== null ? GalleryWithPhotosResource::make($gallery)->resolve() : null;
    }
}
