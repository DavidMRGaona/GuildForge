<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Services;

use App\Application\DTOs\Response\GalleryResponseDTO;
use App\Application\Services\GalleryQueryServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class GalleryQueryServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string LOW_ID = '11111111-1111-4111-8111-111111111111';

    private const string HIGH_ID = 'ffffffff-ffff-4fff-bfff-ffffffffffff';

    private GalleryQueryServiceInterface $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = $this->app->make(GalleryQueryServiceInterface::class);
    }

    /**
     * Inserted in the reverse of the expected order, so ties only come out right when the query breaks them.
     */
    public function test_published_paginated_breaks_ties_by_creation_then_id(): void
    {
        $createdAt = '2026-02-02 09:00:00';

        GalleryModel::factory()->published()->create(['title' => 'Tie oldest', 'created_at' => '2026-02-01 09:00:00']);
        GalleryModel::factory()->published()->create(['id' => self::LOW_ID, 'title' => 'Tie low id', 'created_at' => $createdAt]);
        GalleryModel::factory()->published()->create(['id' => self::HIGH_ID, 'title' => 'Tie high id', 'created_at' => $createdAt]);
        GalleryModel::factory()->published()->create(['title' => 'Newest', 'created_at' => '2026-02-03 09:00:00']);

        $this->assertSame(
            ['Newest', 'Tie high id', 'Tie low id', 'Tie oldest'],
            $this->titles($this->service->getPublishedPaginated()),
        );
    }

    public function test_featured_gallery_breaks_ties_by_creation_then_id(): void
    {
        $updatedAt = '2026-03-01 10:00:00';
        $createdAt = '2026-02-02 09:00:00';

        GalleryModel::factory()->published()->create([
            'title' => 'Tie oldest',
            'created_at' => '2026-02-01 09:00:00',
            'updated_at' => $updatedAt,
        ]);
        GalleryModel::factory()->published()->create([
            'id' => self::LOW_ID,
            'title' => 'Tie low id',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);
        GalleryModel::factory()->published()->create([
            'id' => self::HIGH_ID,
            'title' => 'Tie high id',
            'created_at' => $createdAt,
            'updated_at' => $updatedAt,
        ]);

        $this->assertSame('Tie high id', $this->service->getFeaturedGallery()?->title);
    }

    /**
     * @param  array<GalleryResponseDTO>  $galleries
     * @return list<string>
     */
    private function titles(array $galleries): array
    {
        return array_values(array_map(fn (GalleryResponseDTO $gallery): string => $gallery->title, $galleries));
    }
}
