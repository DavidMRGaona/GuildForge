<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\DTOs\Response\ArticleResponseDTO;
use App\Application\Services\ArticleQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\ArticleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class ArticleQueryServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array EXPECTED_ORDER = ['Campaign newest', 'Campaign tie high id', 'Campaign tie low id', 'Campaign tie oldest'];

    private ArticleQueryServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(ArticleQueryServiceInterface::class);
        $this->createArticlesPublishedAtTheSameTime();
    }

    public function test_latest_published_breaks_ties_by_creation_then_id(): void
    {
        $this->assertSame(self::EXPECTED_ORDER, $this->titles($this->service->getLatestPublished()));
    }

    public function test_published_paginated_breaks_ties_by_creation_then_id(): void
    {
        $this->assertSame(self::EXPECTED_ORDER, $this->titles($this->service->getPublishedPaginated()));
    }

    public function test_search_breaks_ties_by_creation_then_id(): void
    {
        $this->assertSame(self::EXPECTED_ORDER, $this->titles($this->service->searchPublished('campaign')));
    }

    /**
     * Inserted in the reverse of the expected order, so ties only come out right when the query breaks them.
     */
    private function createArticlesPublishedAtTheSameTime(): void
    {
        $publishedAt = '2026-03-01 10:00:00';

        ArticleModel::factory()->published()->create([
            'title' => 'Campaign tie oldest',
            'published_at' => $publishedAt,
            'created_at' => '2026-02-01 09:00:00',
        ]);
        ArticleModel::factory()->published()->create([
            'id' => '11111111-1111-4111-8111-111111111111',
            'title' => 'Campaign tie low id',
            'published_at' => $publishedAt,
            'created_at' => '2026-02-02 09:00:00',
        ]);
        ArticleModel::factory()->published()->create([
            'id' => 'ffffffff-ffff-4fff-bfff-ffffffffffff',
            'title' => 'Campaign tie high id',
            'published_at' => $publishedAt,
            'created_at' => '2026-02-02 09:00:00',
        ]);
        ArticleModel::factory()->published()->create([
            'title' => 'Campaign newest',
            'published_at' => '2026-03-02 10:00:00',
            'created_at' => '2026-01-01 09:00:00',
        ]);
    }

    /**
     * @param  array<ArticleResponseDTO>  $articles
     * @return list<string>
     */
    private function titles(array $articles): array
    {
        return array_values(array_map(fn (ArticleResponseDTO $article): string => $article->title, $articles));
    }
}
