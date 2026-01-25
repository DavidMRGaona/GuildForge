<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PhotoModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_photo_in_database(): void
    {
        $gallery = GalleryModel::factory()->create();
        $photo = PhotoModel::factory()->forGallery($gallery)->create([
            'image_public_id' => 'galleries/warhammer/photo-001.jpg',
            'caption' => 'The final battle.',
            'sort_order' => 5,
        ]);

        $this->assertDatabaseHas('photos', [
            'id' => $photo->id,
            'gallery_id' => $gallery->id,
            'image_public_id' => 'galleries/warhammer/photo-001.jpg',
            'caption' => 'The final battle.',
            'sort_order' => 5,
        ]);
    }

    public function test_it_has_correct_fillable_attributes(): void
    {
        $fillable = [
            'id',
            'gallery_id',
            'image_public_id',
            'caption',
            'sort_order',
        ];

        $model = new PhotoModel();

        $this->assertEquals($fillable, $model->getFillable());
    }

    public function test_it_casts_sort_order_to_integer(): void
    {
        $photo = PhotoModel::factory()->create([
            'sort_order' => 10,
        ]);

        $this->assertEquals(10, $photo->sort_order);
        $this->assertIsInt($photo->sort_order);
    }

    public function test_it_belongs_to_gallery(): void
    {
        $gallery = GalleryModel::factory()->create([
            'title' => 'Test Gallery',
        ]);
        $photo = PhotoModel::factory()->forGallery($gallery)->create();

        $this->assertInstanceOf(GalleryModel::class, $photo->gallery);
        $this->assertEquals('Test Gallery', $photo->gallery->title);
    }

    public function test_photos_are_deleted_when_gallery_is_deleted(): void
    {
        $gallery = GalleryModel::factory()->create();
        $photo = PhotoModel::factory()->forGallery($gallery)->create();

        $photoId = $photo->id;
        $gallery->delete();

        $this->assertDatabaseMissing('photos', [
            'id' => $photoId,
        ]);
    }

    public function test_factory_creates_photo_with_gallery(): void
    {
        $photo = PhotoModel::factory()->create();

        $this->assertNotNull($photo->gallery_id);
        $this->assertInstanceOf(GalleryModel::class, $photo->gallery);
    }
}
