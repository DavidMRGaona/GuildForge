<?php

declare(strict_types=1);

namespace App\Infrastructure\Services;

use App\Application\DTOs\Response\SitemapEntryDTO;
use App\Application\Services\SitemapQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use Illuminate\Support\Collection;

final readonly class SitemapQueryService implements SitemapQueryServiceInterface
{
    public function getAllEntries(): Collection
    {
        return $this->getStaticEntries()
            ->merge($this->getEventEntries())
            ->merge($this->getArticleEntries())
            ->merge($this->getGalleryEntries());
    }

    public function getStaticEntries(): Collection
    {
        return new Collection([
            new SitemapEntryDTO(
                loc: url('/'),
                lastmod: null,
                priority: '1.0',
                changefreq: 'weekly',
            ),
            new SitemapEntryDTO(
                loc: url('/nosotros'),
                lastmod: null,
                priority: '0.8',
                changefreq: 'monthly',
            ),
            new SitemapEntryDTO(
                loc: url('/eventos'),
                lastmod: null,
                priority: '0.9',
                changefreq: 'daily',
            ),
            new SitemapEntryDTO(
                loc: url('/articulos'),
                lastmod: null,
                priority: '0.9',
                changefreq: 'daily',
            ),
            new SitemapEntryDTO(
                loc: url('/galeria'),
                lastmod: null,
                priority: '0.8',
                changefreq: 'weekly',
            ),
        ]);
    }

    public function getEventEntries(): Collection
    {
        return EventModel::query()
            ->select('slug', 'updated_at')
            ->where('is_published', true)
            ->get()
            ->map(fn (EventModel $event) => new SitemapEntryDTO(
                loc: url('/eventos/'.$event->slug),
                lastmod: $event->updated_at?->toAtomString(),
                priority: '0.7',
                changefreq: 'weekly',
            ));
    }

    public function getArticleEntries(): Collection
    {
        return ArticleModel::query()
            ->select('slug', 'updated_at')
            ->where('is_published', true)
            ->get()
            ->map(fn (ArticleModel $article) => new SitemapEntryDTO(
                loc: url('/articulos/'.$article->slug),
                lastmod: $article->updated_at?->toAtomString(),
                priority: '0.7',
                changefreq: 'monthly',
            ));
    }

    public function getGalleryEntries(): Collection
    {
        return GalleryModel::query()
            ->select('slug', 'updated_at')
            ->where('is_published', true)
            ->get()
            ->map(fn (GalleryModel $gallery) => new SitemapEntryDTO(
                loc: url('/galeria/'.$gallery->slug),
                lastmod: $gallery->updated_at?->toAtomString(),
                priority: '0.6',
                changefreq: 'monthly',
            ));
    }
}
