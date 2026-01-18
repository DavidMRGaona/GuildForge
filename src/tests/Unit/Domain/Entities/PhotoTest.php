<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Photo;
use App\Domain\ValueObjects\GalleryId;
use App\Domain\ValueObjects\PhotoId;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PhotoTest extends TestCase
{
    public function test_it_creates_photo_with_required_data(): void
    {
        $id = PhotoId::generate();
        $galleryId = GalleryId::generate();
        $imagePublicId = 'galleries/warhammer/photo-001.jpg';

        $photo = new Photo(
            id: $id,
            galleryId: $galleryId,
            imagePublicId: $imagePublicId,
        );

        $this->assertEquals($id, $photo->id());
        $this->assertEquals($galleryId, $photo->galleryId());
        $this->assertEquals($imagePublicId, $photo->imagePublicId());
        $this->assertNull($photo->caption());
        $this->assertEquals(0, $photo->sortOrder());
    }

    public function test_it_creates_photo_with_all_data(): void
    {
        $id = PhotoId::generate();
        $galleryId = GalleryId::generate();
        $imagePublicId = 'galleries/dnd/campaign-session-05.jpg';
        $caption = 'The party defeating the dragon.';
        $sortOrder = 5;
        $createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2024-01-02 15:30:00');

        $photo = new Photo(
            id: $id,
            galleryId: $galleryId,
            imagePublicId: $imagePublicId,
            caption: $caption,
            sortOrder: $sortOrder,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        $this->assertEquals($id, $photo->id());
        $this->assertEquals($galleryId, $photo->galleryId());
        $this->assertEquals($imagePublicId, $photo->imagePublicId());
        $this->assertEquals($caption, $photo->caption());
        $this->assertEquals($sortOrder, $photo->sortOrder());
        $this->assertEquals($createdAt, $photo->createdAt());
        $this->assertEquals($updatedAt, $photo->updatedAt());
    }

    public function test_it_has_default_sort_order_of_zero(): void
    {
        $photo = $this->createPhoto();

        $this->assertEquals(0, $photo->sortOrder());
    }

    public function test_it_updates_sort_order(): void
    {
        $photo = $this->createPhoto();

        $photo->updateSortOrder(10);

        $this->assertEquals(10, $photo->sortOrder());
    }

    public function test_it_updates_sort_order_to_zero(): void
    {
        $photo = $this->createPhoto(sortOrder: 5);

        $photo->updateSortOrder(0);

        $this->assertEquals(0, $photo->sortOrder());
    }

    private function createPhoto(
        int $sortOrder = 0,
    ): Photo {
        return new Photo(
            id: PhotoId::generate(),
            galleryId: GalleryId::generate(),
            imagePublicId: 'galleries/test/photo.jpg',
            caption: null,
            sortOrder: $sortOrder,
        );
    }
}
