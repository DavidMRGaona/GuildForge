<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Gallery;
use App\Domain\ValueObjects\GalleryId;
use App\Domain\ValueObjects\Slug;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class GalleryTest extends TestCase
{
    public function test_it_creates_gallery_with_required_data(): void
    {
        $id = GalleryId::generate();
        $title = 'Warhammer Tournament 2024';
        $slug = new Slug('warhammer-tournament-2024');

        $gallery = new Gallery(
            id: $id,
            title: $title,
            slug: $slug,
        );

        $this->assertEquals($id, $gallery->id());
        $this->assertEquals($title, $gallery->title());
        $this->assertEquals($slug, $gallery->slug());
        $this->assertNull($gallery->description());
        $this->assertNull($gallery->coverImagePublicId());
        $this->assertFalse($gallery->isPublished());
    }

    public function test_it_creates_gallery_with_all_data(): void
    {
        $id = GalleryId::generate();
        $title = 'D&D Campaign Photos';
        $slug = new Slug('dnd-campaign-photos');
        $description = 'Photos from our weekly campaign sessions.';
        $coverImagePublicId = 'galleries/dnd-campaign-cover.jpg';
        $isPublished = true;
        $createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2024-01-02 15:30:00');

        $gallery = new Gallery(
            id: $id,
            title: $title,
            slug: $slug,
            description: $description,
            coverImagePublicId: $coverImagePublicId,
            isPublished: $isPublished,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        $this->assertEquals($id, $gallery->id());
        $this->assertEquals($title, $gallery->title());
        $this->assertEquals($slug, $gallery->slug());
        $this->assertEquals($description, $gallery->description());
        $this->assertEquals($coverImagePublicId, $gallery->coverImagePublicId());
        $this->assertTrue($gallery->isPublished());
        $this->assertEquals($createdAt, $gallery->createdAt());
        $this->assertEquals($updatedAt, $gallery->updatedAt());
    }

    public function test_it_is_unpublished_by_default(): void
    {
        $gallery = $this->createGallery();

        $this->assertFalse($gallery->isPublished());
    }

    public function test_it_publishes_gallery(): void
    {
        $gallery = $this->createGallery();

        $gallery->publish();

        $this->assertTrue($gallery->isPublished());
    }

    public function test_it_unpublishes_gallery(): void
    {
        $gallery = $this->createGallery(isPublished: true);

        $gallery->unpublish();

        $this->assertFalse($gallery->isPublished());
    }

    private function createGallery(
        bool $isPublished = false,
    ): Gallery {
        return new Gallery(
            id: GalleryId::generate(),
            title: 'Test Gallery',
            slug: new Slug('test-gallery'),
            description: null,
            isPublished: $isPublished,
        );
    }
}
