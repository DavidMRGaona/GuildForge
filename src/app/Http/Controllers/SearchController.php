<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class SearchController extends Controller
{
    private const MIN_QUERY_LENGTH = 2;
    private const MAX_RESULTS = 12;

    public function __invoke(Request $request): Response
    {
        $query = trim((string) $request->query('q', ''));

        // Empty query returns empty results
        if ($query === '') {
            return Inertia::render('Search/Index', [
                'query' => '',
                'events' => [],
                'articles' => [],
                'error' => null,
            ]);
        }

        // Query too short
        if (mb_strlen($query) < self::MIN_QUERY_LENGTH) {
            return Inertia::render('Search/Index', [
                'query' => $query,
                'events' => [],
                'articles' => [],
                'error' => 'minChars',
            ]);
        }

        // Search events
        $events = EventModel::query()
            ->where('is_published', true)
            ->where(function ($q) use ($query): void {
                $lowerQuery = mb_strtolower($query);
                $q->whereRaw('LOWER(title) LIKE ?', ['%' . $lowerQuery . '%'])
                    ->orWhereRaw('LOWER(description) LIKE ?', ['%' . $lowerQuery . '%']);
            })
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn (EventModel $event): array => $this->mapEventModel($event))
            ->toArray();

        // Search articles
        $articles = ArticleModel::query()
            ->where('is_published', true)
            ->with('author')
            ->where(function ($q) use ($query): void {
                $lowerQuery = mb_strtolower($query);
                $q->whereRaw('LOWER(title) LIKE ?', ['%' . $lowerQuery . '%'])
                    ->orWhereRaw('LOWER(content) LIKE ?', ['%' . $lowerQuery . '%'])
                    ->orWhereRaw('LOWER(excerpt) LIKE ?', ['%' . $lowerQuery . '%']);
            })
            ->limit(self::MAX_RESULTS)
            ->get()
            ->map(fn (ArticleModel $article): array => $this->mapArticleModel($article))
            ->toArray();

        return Inertia::render('Search/Index', [
            'query' => $query,
            'events' => $events,
            'articles' => $articles,
            'error' => null,
        ]);
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
            'imagePublicId' => $event->image_public_id,
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
            'excerpt' => $article->excerpt,
            'content' => $article->content,
            'featuredImagePublicId' => $article->featured_image_public_id,
            'publishedAt' => $article->published_at?->format('c'),
            'author' => $this->mapAuthor($article->author),
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
        ];
    }
}
