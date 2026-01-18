<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\ValueObjects\ArticleId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ArticleIdTest extends TestCase
{
    public function test_it_generates_new_id(): void
    {
        $id = ArticleId::generate();

        $this->assertInstanceOf(ArticleId::class, $id);
        $this->assertNotEmpty((string) $id);
    }

    public function test_it_creates_from_valid_uuid_string(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $id = ArticleId::fromString($uuid);

        $this->assertEquals($uuid, (string) $id);
    }

    public function test_it_throws_exception_for_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ArticleId::fromString('invalid-uuid');
    }

    public function test_it_returns_uuid_as_string(): void
    {
        $id = ArticleId::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $id
        );
    }

    public function test_two_ids_with_same_value_are_equal(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $id1 = ArticleId::fromString($uuid);
        $id2 = ArticleId::fromString($uuid);

        $this->assertTrue($id1->equals($id2));
    }

    public function test_two_different_ids_are_not_equal(): void
    {
        $id1 = ArticleId::generate();
        $id2 = ArticleId::generate();

        $this->assertFalse($id1->equals($id2));
    }

    public function test_id_value_property_returns_uuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $id = ArticleId::fromString($uuid);

        $this->assertEquals($uuid, $id->value);
    }
}
