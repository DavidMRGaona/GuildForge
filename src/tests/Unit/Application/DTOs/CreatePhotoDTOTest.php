<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\CreatePhotoDTO;
use PHPUnit\Framework\TestCase;

final class CreatePhotoDTOTest extends TestCase
{
    public function test_it_creates_dto_with_required_data(): void
    {
        $galleryId = '550e8400-e29b-41d4-a716-446655440000';
        $imagePublicId = 'galleries/warhammer/photo-001.jpg';

        $dto = new CreatePhotoDTO(
            galleryId: $galleryId,
            imagePublicId: $imagePublicId,
        );

        $this->assertEquals($galleryId, $dto->galleryId);
        $this->assertEquals($imagePublicId, $dto->imagePublicId);
        $this->assertNull($dto->caption);
        $this->assertEquals(0, $dto->sortOrder);
    }

    public function test_it_creates_dto_with_all_data(): void
    {
        $galleryId = '550e8400-e29b-41d4-a716-446655440000';
        $imagePublicId = 'galleries/dnd/campaign-session.jpg';
        $caption = 'The party defeating the dragon.';
        $sortOrder = 5;

        $dto = new CreatePhotoDTO(
            galleryId: $galleryId,
            imagePublicId: $imagePublicId,
            caption: $caption,
            sortOrder: $sortOrder,
        );

        $this->assertEquals($galleryId, $dto->galleryId);
        $this->assertEquals($imagePublicId, $dto->imagePublicId);
        $this->assertEquals($caption, $dto->caption);
        $this->assertEquals($sortOrder, $dto->sortOrder);
    }

    public function test_it_creates_dto_from_array(): void
    {
        $data = [
            'gallery_id' => '550e8400-e29b-41d4-a716-446655440000',
            'image_public_id' => 'galleries/test/photo.jpg',
            'caption' => 'Test photo caption.',
            'sort_order' => 10,
        ];

        $dto = CreatePhotoDTO::fromArray($data);

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $dto->galleryId);
        $this->assertEquals('galleries/test/photo.jpg', $dto->imagePublicId);
        $this->assertEquals('Test photo caption.', $dto->caption);
        $this->assertEquals(10, $dto->sortOrder);
    }

    public function test_it_creates_dto_from_array_with_only_required_fields(): void
    {
        $data = [
            'gallery_id' => '550e8400-e29b-41d4-a716-446655440000',
            'image_public_id' => 'galleries/minimal/photo.jpg',
        ];

        $dto = CreatePhotoDTO::fromArray($data);

        $this->assertEquals('550e8400-e29b-41d4-a716-446655440000', $dto->galleryId);
        $this->assertEquals('galleries/minimal/photo.jpg', $dto->imagePublicId);
        $this->assertNull($dto->caption);
        $this->assertEquals(0, $dto->sortOrder);
    }
}
