<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\Response\TagResponseDTO;
use PHPUnit\Framework\TestCase;

final class TagResponseDTOTest extends TestCase
{
    public function test_it_creates_dto_with_all_properties(): void
    {
        $id = 'tag-uuid-123';
        $name = 'Warhammer 40k';
        $slug = 'warhammer-40k';
        $parentId = 'parent-uuid-456';
        $parentName = 'Miniature Games';
        $appliesTo = ['events', 'articles'];
        $color = '#FF5733';
        $sortOrder = 10;

        $dto = new TagResponseDTO(
            id: $id,
            name: $name,
            slug: $slug,
            parentId: $parentId,
            parentName: $parentName,
            appliesTo: $appliesTo,
            color: $color,
            sortOrder: $sortOrder,
        );

        $this->assertEquals($id, $dto->id);
        $this->assertEquals($name, $dto->name);
        $this->assertEquals($slug, $dto->slug);
        $this->assertEquals($parentId, $dto->parentId);
        $this->assertEquals($parentName, $dto->parentName);
        $this->assertEquals($appliesTo, $dto->appliesTo);
        $this->assertEquals($color, $dto->color);
        $this->assertEquals($sortOrder, $dto->sortOrder);
    }

    public function test_it_creates_dto_without_parent(): void
    {
        $dto = new TagResponseDTO(
            id: 'tag-uuid-789',
            name: 'RPG',
            slug: 'rpg',
            parentId: null,
            parentName: null,
            appliesTo: ['events', 'articles', 'galleries'],
            color: '#00AA00',
            sortOrder: 0,
        );

        $this->assertNull($dto->parentId);
        $this->assertNull($dto->parentName);
    }

    public function test_it_creates_dto_with_single_applies_to(): void
    {
        $dto = new TagResponseDTO(
            id: 'tag-uuid-101',
            name: 'Event Only Tag',
            slug: 'event-only-tag',
            parentId: null,
            parentName: null,
            appliesTo: ['events'],
            color: '#0000FF',
            sortOrder: 5,
        );

        $this->assertCount(1, $dto->appliesTo);
        $this->assertEquals(['events'], $dto->appliesTo);
    }

    public function test_it_creates_dto_with_empty_applies_to(): void
    {
        $dto = new TagResponseDTO(
            id: 'tag-uuid-202',
            name: 'No Type Tag',
            slug: 'no-type-tag',
            parentId: null,
            parentName: null,
            appliesTo: [],
            color: '#CCCCCC',
            sortOrder: 0,
        );

        $this->assertIsArray($dto->appliesTo);
        $this->assertEmpty($dto->appliesTo);
    }

    public function test_it_creates_dto_with_zero_sort_order(): void
    {
        $dto = new TagResponseDTO(
            id: 'tag-uuid-303',
            name: 'First Tag',
            slug: 'first-tag',
            parentId: null,
            parentName: null,
            appliesTo: ['events'],
            color: '#000000',
            sortOrder: 0,
        );

        $this->assertEquals(0, $dto->sortOrder);
        $this->assertIsInt($dto->sortOrder);
    }

    public function test_it_is_readonly(): void
    {
        $dto = new TagResponseDTO(
            id: 'tag-uuid-404',
            name: 'Test Tag',
            slug: 'test-tag',
            parentId: null,
            parentName: null,
            appliesTo: ['events'],
            color: '#FFFFFF',
            sortOrder: 1,
        );

        $reflection = new \ReflectionClass($dto);
        $this->assertTrue($reflection->isReadOnly());
    }
}
