<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class GalleryModelTest extends TestCase
{
    use RefreshDatabase;

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
            'cover_image_public_id',
            'is_published',
        ];

        $model = new GalleryModel();

        $this->assertEquals($fillable, $model->getFillable());
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
}
