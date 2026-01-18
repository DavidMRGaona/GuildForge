<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;

final class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $urls = $this->buildUrlCollection();

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * @return Collection<int, array{loc: string, lastmod: string|null, priority: string, changefreq: string}>
     */
    private function buildUrlCollection(): Collection
    {
        $urls = new Collection();

        // Add static URLs
        $urls->push([
            'loc' => url('/'),
            'lastmod' => null,
            'priority' => '1.0',
            'changefreq' => 'weekly',
        ]);

        $urls->push([
            'loc' => url('/nosotros'),
            'lastmod' => null,
            'priority' => '0.8',
            'changefreq' => 'monthly',
        ]);

        $urls->push([
            'loc' => url('/eventos'),
            'lastmod' => null,
            'priority' => '0.9',
            'changefreq' => 'daily',
        ]);

        $urls->push([
            'loc' => url('/articulos'),
            'lastmod' => null,
            'priority' => '0.9',
            'changefreq' => 'daily',
        ]);

        $urls->push([
            'loc' => url('/galeria'),
            'lastmod' => null,
            'priority' => '0.8',
            'changefreq' => 'weekly',
        ]);

        // Add published events
        EventModel::query()
            ->select('slug', 'updated_at')
            ->where('is_published', true)
            ->get()
            ->each(function (EventModel $event) use ($urls): void {
                $urls->push([
                    'loc' => url('/eventos/' . $event->slug),
                    'lastmod' => $event->updated_at?->toAtomString(),
                    'priority' => '0.7',
                    'changefreq' => 'weekly',
                ]);
            });

        // Add published articles
        ArticleModel::query()
            ->select('slug', 'updated_at')
            ->where('is_published', true)
            ->get()
            ->each(function (ArticleModel $article) use ($urls): void {
                $urls->push([
                    'loc' => url('/articulos/' . $article->slug),
                    'lastmod' => $article->updated_at?->toAtomString(),
                    'priority' => '0.7',
                    'changefreq' => 'monthly',
                ]);
            });

        // Add published galleries
        GalleryModel::query()
            ->select('slug', 'updated_at')
            ->where('is_published', true)
            ->get()
            ->each(function (GalleryModel $gallery) use ($urls): void {
                $urls->push([
                    'loc' => url('/galeria/' . $gallery->slug),
                    'lastmod' => $gallery->updated_at?->toAtomString(),
                    'priority' => '0.6',
                    'changefreq' => 'monthly',
                ]);
            });

        return $urls;
    }
}
