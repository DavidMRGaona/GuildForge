<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteNews;

use App\Application\Services\TimezoneServiceInterface;
use App\Application\SiteNews\DTOs\SiteNewsItemDTO;
use App\Application\SiteNews\Services\SiteNewsReaderInterface;
use App\Domain\SiteNews\SiteNewsKind;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Search\Support\PlainText;
use Illuminate\Support\Str;

/**
 * Published means is_published, the same flag every public page reads: unapproved proposals are
 * never stored as published (HasProposalReview).
 */
final readonly class EloquentSiteNewsReader implements SiteNewsReaderInterface
{
    public function __construct(
        private TimezoneServiceInterface $timezone,
    ) {}

    public function find(SiteNewsKind $kind, string $id): ?SiteNewsItemDTO
    {
        // Keys are uuids: anything else matches no row, and PostgreSQL rejects it in the query
        if (! Str::isUuid($id)) {
            return null;
        }

        return match ($kind) {
            SiteNewsKind::Event => $this->event($id),
            SiteNewsKind::Article => $this->article($id),
            SiteNewsKind::Gallery => $this->gallery($id),
        };
    }

    private function event(string $id): ?SiteNewsItemDTO
    {
        $event = EventModel::query()->where('is_published', true)->find($id);

        if (! $event instanceof EventModel) {
            return null;
        }

        $when = $this->timezone->format($event->start_date);
        $location = trim((string) $event->location);

        return new SiteNewsItemDTO(
            kind: SiteNewsKind::Event,
            id: $id,
            title: $event->title,
            url: route('events.show', $event->slug, false),
            summary: $location === '' ? $when : $when.' · '.$location,
        );
    }

    private function article(string $id): ?SiteNewsItemDTO
    {
        $article = ArticleModel::query()->where('is_published', true)->find($id);

        if (! $article instanceof ArticleModel) {
            return null;
        }

        $excerpt = PlainText::from((string) $article->excerpt);

        return new SiteNewsItemDTO(
            kind: SiteNewsKind::Article,
            id: $id,
            title: $article->title,
            url: route('articles.show', $article->slug, false),
            summary: $this->summary($excerpt !== '' ? $excerpt : PlainText::from((string) $article->content)),
        );
    }

    private function gallery(string $id): ?SiteNewsItemDTO
    {
        $gallery = GalleryModel::query()->where('is_published', true)->find($id);

        if (! $gallery instanceof GalleryModel) {
            return null;
        }

        return new SiteNewsItemDTO(
            kind: SiteNewsKind::Gallery,
            id: $id,
            title: $gallery->title,
            url: route('galleries.show', $gallery->slug, false),
            summary: $this->summary(PlainText::from((string) $gallery->description)),
        );
    }

    private function summary(string $text): ?string
    {
        return $text === '' ? null : $text;
    }
}
