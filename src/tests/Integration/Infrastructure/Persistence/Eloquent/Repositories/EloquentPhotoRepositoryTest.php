<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\Entities\Photo;
use App\Domain\Repositories\PhotoRepositoryInterface;
use App\Domain\ValueObjects\GalleryId;
use App\Domain\ValueObjects\PhotoId;
use App\Infrastructure\Persistence\Eloquent\Models\GalleryModel;
use App\Infrastructure\Persistence\Eloquent\Models\PhotoModel;
use App\Infrastructure\Persistence\Eloquent\Repositories\EloquentPhotoRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EloquentPhotoRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private EloquentPhotoRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new EloquentPhotoRepository;
    }

    public function test_it_implements_photo_repository_interface(): void
    {
        $this->assertInstanceOf(PhotoRepositoryInterface::class, $this->repository);
    }

    public function test_it_finds_photo_by_id(): void
    {
        $gallery = GalleryModel::factory()->create();
        $model = PhotoModel::factory()->forGallery($gallery)->create([
            'image_public_id' => 'galleries/test/photo.jpg',
            'caption' => 'Test caption',
        ]);

        $photo = $this->repository->findById(new PhotoId($model->id));

        $this->assertNotNull($photo);
        $this->assertEquals($model->id, $photo->id()->value);
        $this->assertEquals('galleries/test/photo.jpg', $photo->imagePublicId());
        $this->assertEquals('Test caption', $photo->caption());
    }

    public function test_it_returns_null_when_not_found(): void
    {
        $photo = $this->repository->findById(PhotoId::generate());

        $this->assertNull($photo);
    }

    public function test_it_finds_photos_by_gallery_id(): void
    {
        $gallery = GalleryModel::factory()->create();
        $otherGallery = GalleryModel::factory()->create();

        PhotoModel::factory()->count(3)->forGallery($gallery)->create();
        PhotoModel::factory()->count(2)->forGallery($otherGallery)->create();

        $photos = $this->repository->findByGalleryId(new GalleryId($gallery->id));

        $this->assertCount(3, $photos);
        $photos->each(function (Photo $photo) use ($gallery) {
            $this->assertEquals($gallery->id, $photo->galleryId()->value);
        });
    }

    public function test_it_orders_photos_by_sort_order(): void
    {
        $gallery = GalleryModel::factory()->create();
        PhotoModel::factory()->forGallery($gallery)->create(['sort_order' => 3]);
        PhotoModel::factory()->forGallery($gallery)->create(['sort_order' => 1]);
        PhotoModel::factory()->forGallery($gallery)->create(['sort_order' => 2]);

        $photos = $this->repository->findByGalleryId(new GalleryId($gallery->id));

        $sortOrders = $photos->map(fn (Photo $photo): int => $photo->sortOrder())->toArray();
        $this->assertEquals([1, 2, 3], $sortOrders);
    }

    public function test_it_saves_new_photo(): void
    {
        $gallery = GalleryModel::factory()->create();
        $id = PhotoId::generate();
        $photo = new Photo(
            id: $id,
            galleryId: new GalleryId($gallery->id),
            imagePublicId: 'galleries/new/photo.jpg',
            caption: 'New photo caption.',
            sortOrder: 5,
        );

        $this->repository->save($photo);

        $this->assertDatabaseHas('photos', [
            'id' => $id->value,
            'gallery_id' => $gallery->id,
            'image_public_id' => 'galleries/new/photo.jpg',
            'caption' => 'New photo caption.',
            'sort_order' => 5,
        ]);
    }

    public function test_it_updates_existing_photo(): void
    {
        $gallery = GalleryModel::factory()->create();
        $model = PhotoModel::factory()->forGallery($gallery)->create([
            'image_public_id' => 'galleries/old/photo.jpg',
            'caption' => 'Old caption',
            'sort_order' => 1,
        ]);

        $photo = new Photo(
            id: new PhotoId($model->id),
            galleryId: new GalleryId($gallery->id),
            imagePublicId: 'galleries/updated/photo.jpg',
            caption: 'Updated caption',
            sortOrder: 10,
        );

        $this->repository->save($photo);

        $this->assertDatabaseHas('photos', [
            'id' => $model->id,
            'image_public_id' => 'galleries/updated/photo.jpg',
            'caption' => 'Updated caption',
            'sort_order' => 10,
        ]);

        $this->assertDatabaseMissing('photos', [
            'image_public_id' => 'galleries/old/photo.jpg',
        ]);
    }

    public function test_it_deletes_photo(): void
    {
        $gallery = GalleryModel::factory()->create();
        $model = PhotoModel::factory()->forGallery($gallery)->create();
        $photo = $this->repository->findById(new PhotoId($model->id));

        $this->assertNotNull($photo);

        $this->repository->delete($photo);

        $this->assertDatabaseMissing('photos', [
            'id' => $model->id,
        ]);
    }

    public function test_it_deletes_photos_by_gallery_id(): void
    {
        $gallery = GalleryModel::factory()->create();
        $otherGallery = GalleryModel::factory()->create();

        PhotoModel::factory()->count(3)->forGallery($gallery)->create();
        PhotoModel::factory()->count(2)->forGallery($otherGallery)->create();

        $this->repository->deleteByGalleryId(new GalleryId($gallery->id));

        $this->assertDatabaseCount('photos', 2);
        $this->assertDatabaseMissing('photos', [
            'gallery_id' => $gallery->id,
        ]);
    }
}
