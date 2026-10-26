<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class GalleryModelTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array TIED_PHOTOS_ORDER = ['Tie oldest', 'Tie low id', 'Tie high id', 'Later'];

    public function test_it_creates_gallery_in_database(): void
    {
        $gallery = GalleryModel::factory()->create([
            'title' => 'Warhammer Tournament 2024',
            'slug' => 'warhammer-tournament-2024',
            'description' => 'Photos from the annual tournament.',
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('galleries', [
            'id' => $gallery->id,
            'title' => 'Warhammer Tournament 2024',
            'slug' => 'warhammer-tournament-2024',
            'is_published' => true,
        ]);
    }

    public function test_it_has_correct_fillable_attributes(): void
    {
        $fillable = [
            'id',
            'title',
            'slug',
            'description',
            'is_published',
            'is_featured',
        ];

        $model = new GalleryModel;

        $this->assertEquals($fillable, $model->getFillable());
    }

    public function test_cover_image_public_id_returns_first_photo_image(): void
    {
        $gallery = GalleryModel::factory()->create();
        PhotoModel::factory()->forGallery($gallery)->create([
            'image_public_id' => 'galleries/second.jpg',
            'sort_order' => 2,
        ]);
        PhotoModel::factory()->forGallery($gallery)->create([
            'image_public_id' => 'galleries/first.jpg',
            'sort_order' => 1,
        ]);

        $this->assertEquals('galleries/first.jpg', $gallery->cover_image_public_id);
    }

    public function test_cover_image_public_id_returns_null_when_no_photos(): void
    {
        $gallery = GalleryModel::factory()->create();

        $this->assertNull($gallery->cover_image_public_id);
    }

    public function test_it_casts_boolean_correctly(): void
    {
        $gallery = GalleryModel::factory()->create([
            'is_published' => true,
        ]);

        $this->assertTrue($gallery->is_published);
        $this->assertIsBool($gallery->is_published);
    }

    public function test_factory_creates_draft_gallery_by_default(): void
    {
        $gallery = GalleryModel::factory()->create();

        $this->assertFalse($gallery->is_published);
    }

    public function test_factory_published_state_creates_published_gallery(): void
    {
        $gallery = GalleryModel::factory()->published()->create();

        $this->assertTrue($gallery->is_published);
    }

    public function test_it_has_many_photos(): void
    {
        $gallery = GalleryModel::factory()->create();
        PhotoModel::factory()->count(3)->forGallery($gallery)->create();

        $this->assertCount(3, $gallery->photos);
        $this->assertInstanceOf(PhotoModel::class, $gallery->photos->first());
    }

    public function test_photos_are_ordered_by_sort_order(): void
    {
        $gallery = GalleryModel::factory()->create();
        PhotoModel::factory()->forGallery($gallery)->create(['sort_order' => 3]);
        PhotoModel::factory()->forGallery($gallery)->create(['sort_order' => 1]);
        PhotoModel::factory()->forGallery($gallery)->create(['sort_order' => 2]);

        $gallery->refresh();
        $sortOrders = $gallery->photos->pluck('sort_order')->toArray();

        $this->assertEquals([1, 2, 3], $sortOrders);
    }

    public function test_photos_break_sort_order_ties_by_creation_then_id(): void
    {
        $gallery = $this->createGalleryWithTiedPhotos();

        $this->assertSame(self::TIED_PHOTOS_ORDER, $gallery->photos->pluck('caption')->all());
    }

    public function test_eager_loaded_photos_break_sort_order_ties_by_creation_then_id(): void
    {
        $gallery = $this->createGalleryWithTiedPhotos();

        $loaded = GalleryModel::query()->with('photos')->whereKey($gallery->id)->firstOrFail();

        $this->assertSame(self::TIED_PHOTOS_ORDER, $loaded->photos->pluck('caption')->all());
    }

    public function test_cover_image_breaks_sort_order_ties_by_creation_then_id(): void
    {
        $gallery = $this->createGalleryWithTiedPhotos();

        $this->assertSame('galleries/tie-oldest.jpg', $gallery->cover_image_public_id);
    }

    /**
     * The relation's ORDER BY must not break aggregates: PostgreSQL rejects an ungrouped ORDER BY column.
     */
    public function test_photos_can_be_aggregated_through_the_relation(): void
    {
        $gallery = $this->createGalleryWithTiedPhotos();

        $this->assertSame(4, $gallery->photos()->count());
        $this->assertSame(4, GalleryModel::query()->withCount('photos')->whereKey($gallery->id)->firstOrFail()->photos_count);
    }

    /**
     * Inserted in the reverse of the expected order, so ties only come out right when the query breaks them.
     */
    private function createGalleryWithTiedPhotos(): GalleryModel
    {
        $gallery = GalleryModel::factory()->create();
        $createdAt = '2026-02-02 09:00:00';

        PhotoModel::factory()->forGallery($gallery)->create(['caption' => 'Later', 'sort_order' => 1]);
        PhotoModel::factory()->forGallery($gallery)->create([
            'id' => 'ffffffff-ffff-4fff-bfff-ffffffffffff',
            'caption' => 'Tie high id',
            'sort_order' => 0,
            'created_at' => $createdAt,
        ]);
        PhotoModel::factory()->forGallery($gallery)->create([
            'id' => '11111111-1111-4111-8111-111111111111',
            'caption' => 'Tie low id',
            'sort_order' => 0,
            'created_at' => $createdAt,
        ]);
        PhotoModel::factory()->forGallery($gallery)->create([
            'image_public_id' => 'galleries/tie-oldest.jpg',
            'caption' => 'Tie oldest',
            'sort_order' => 0,
            'created_at' => '2026-02-01 09:00:00',
        ]);

        return $gallery;
    }
}
