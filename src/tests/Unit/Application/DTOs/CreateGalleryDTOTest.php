<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\CreateGalleryDTO;
use PHPUnit\Framework\TestCase;

final class CreateGalleryDTOTest extends TestCase
{
    public function test_it_creates_dto_with_required_data(): void
    {
        $title = 'Warhammer Tournament 2024';

        $dto = new CreateGalleryDTO(
            title: $title,
        );

        $this->assertEquals($title, $dto->title);
        $this->assertNull($dto->description);
        $this->assertNull($dto->coverImagePublicId);
    }

    public function test_it_creates_dto_with_all_data(): void
    {
        $title = 'D&D Campaign Photos';
        $description = 'Photos from our weekly campaign sessions.';
        $coverImagePublicId = 'galleries/dnd-campaign-cover.jpg';

        $dto = new CreateGalleryDTO(
            title: $title,
            description: $description,
            coverImagePublicId: $coverImagePublicId,
        );

        $this->assertEquals($title, $dto->title);
        $this->assertEquals($description, $dto->description);
        $this->assertEquals($coverImagePublicId, $dto->coverImagePublicId);
    }

    public function test_it_creates_dto_from_array(): void
    {
        $data = [
            'title' => 'Board Games Night',
            'description' => 'Monthly board games event photos.',
            'cover_image_public_id' => 'galleries/board-games-cover.jpg',
        ];

        $dto = CreateGalleryDTO::fromArray($data);

        $this->assertEquals('Board Games Night', $dto->title);
        $this->assertEquals('Monthly board games event photos.', $dto->description);
        $this->assertEquals('galleries/board-games-cover.jpg', $dto->coverImagePublicId);
    }

    public function test_it_creates_dto_from_array_with_only_required_fields(): void
    {
        $data = [
            'title' => 'Minimal Gallery',
        ];

        $dto = CreateGalleryDTO::fromArray($data);

        $this->assertEquals('Minimal Gallery', $dto->title);
        $this->assertNull($dto->description);
        $this->assertNull($dto->coverImagePublicId);
    }
}
