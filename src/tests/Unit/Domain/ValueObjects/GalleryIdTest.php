<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\GalleryId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class GalleryIdTest extends TestCase
{
    public function test_it_generates_new_id(): void
    {
        $id = GalleryId::generate();

        $this->assertInstanceOf(GalleryId::class, $id);
        $this->assertNotEmpty((string) $id);
    }

    public function test_it_creates_from_valid_uuid_string(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $id = GalleryId::fromString($uuid);

        $this->assertEquals($uuid, (string) $id);
    }

    public function test_it_throws_exception_for_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        GalleryId::fromString('invalid-uuid');
    }

    public function test_it_returns_uuid_as_string(): void
    {
        $id = GalleryId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $id
        );
    }

    public function test_two_ids_with_same_value_are_equal(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $id1 = GalleryId::fromString($uuid);
        $id2 = GalleryId::fromString($uuid);

        $this->assertTrue($id1->equals($id2));
    }

    public function test_two_different_ids_are_not_equal(): void
    {
        $id1 = GalleryId::generate();
        $id2 = GalleryId::generate();

        $this->assertFalse($id1->equals($id2));
    }

    public function test_id_value_property_returns_uuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $id = GalleryId::fromString($uuid);

        $this->assertEquals($uuid, $id->value);
    }
}
