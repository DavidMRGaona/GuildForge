<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\HeroSlideModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

final class HomeController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Home', [
            'heroSlides' => $this->getHeroSlides(),
            'upcomingEvents' => $this->getUpcomingEvents(),
            'latestArticles' => $this->getLatestArticles(),
            'featuredGallery' => $this->getFeaturedGallery(),
        ]);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function getUpcomingEvents(): Collection
    {
        return EventModel::query()
            ->where('is_published', true)
            ->where('start_date', '>=', now())
            ->orderBy('start_date', 'asc')
            ->limit(3)
            ->get()
            ->map(fn (EventModel $event): array => $this->mapEventModel($event));
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function getLatestArticles(): Collection
    {
        return ArticleModel::query()
            ->where('is_published', true)
            ->with('author')
            ->orderBy('published_at', 'desc')
            ->limit(3)
            ->get()
            ->map(fn (ArticleModel $article): array => $this->mapArticleModel($article));
    }

    /**
     * @return array<string, mixed>|null
     */
    private function getFeaturedGallery(): ?array
    {
        $gallery = GalleryModel::query()
            ->where('is_published', true)
            ->with(['photos' => fn ($query) => $query->orderBy('sort_order')->limit(6)])
            ->withCount('photos')
            ->orderByDesc('is_featured')
            ->orderByDesc('updated_at')
            ->first();

        if ($gallery === null) {
            return null;
        }

        return $this->mapGalleryModel($gallery);
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function getHeroSlides(): Collection
    {
        return HeroSlideModel::query()
            ->activeOrdered()
            ->get()
            ->map(fn (HeroSlideModel $slide): array => $this->mapHeroSlideModel($slide));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapEventModel(EventModel $event): array
    {
        return [
            'id' => $event->id,
            'title' => $event->title,
            'slug' => $event->slug,
            'description' => $event->description,
            'startDate' => $event->start_date->format('c'),
            'endDate' => $event->end_date?->format('c'),
            'location' => $event->location,
            'memberPrice' => $event->member_price !== null ? floatval($event->member_price) : null,
            'nonMemberPrice' => $event->non_member_price !== null ? floatval($event->non_member_price) : null,
            'imagePublicId' => $event->image_public_id,
            'isPublished' => $event->is_published,
            'createdAt' => $event->created_at?->format('c'),
            'updatedAt' => $event->updated_at?->format('c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapArticleModel(ArticleModel $article): array
    {
        return [
            'id' => $article->id,
            'title' => $article->title,
            'slug' => $article->slug,
            'content' => $article->content,
            'excerpt' => $article->excerpt,
            'featuredImagePublicId' => $article->featured_image_public_id,
            'isPublished' => $article->is_published,
            'publishedAt' => $article->published_at?->format('c'),
            'author' => $this->mapAuthor($article->author),
            'createdAt' => $article->created_at?->format('c'),
            'updatedAt' => $article->updated_at?->format('c'),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapAuthor(?UserModel $author): ?array
    {
        if ($author === null) {
            return null;
        }

        return [
            'id' => $author->id,
            'name' => $author->name,
            'displayName' => $author->display_name,
            'avatarPublicId' => $author->avatar_public_id,
        ];
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
            'photos' => $gallery->photos->map(fn (PhotoModel $photo): array => [
                'id' => $photo->id,
                'imagePublicId' => $photo->image_public_id,
                'caption' => $photo->caption,
                'sortOrder' => $photo->sort_order,
            ])->toArray(),
            'createdAt' => $gallery->created_at?->format('c'),
            'updatedAt' => $gallery->updated_at?->format('c'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function mapHeroSlideModel(HeroSlideModel $slide): array
    {
        return [
            'id' => $slide->id,
            'title' => $slide->title,
            'subtitle' => $slide->subtitle,
            'buttonText' => $slide->button_text,
            'buttonUrl' => $slide->button_url,
            'imagePublicId' => $slide->image_public_id,
        ];
    }
}
