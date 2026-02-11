<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Mail\ValueObjects;

use App\Domain\Mail\ValueObjects\EmailLogId;
use App\Domain\ValueObjects\EntityId;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class EmailLogIdTest extends TestCase
{
    public function test_it_creates_from_valid_uuid(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $id = EmailLogId::fromString($uuid);

        $this->assertInstanceOf(EmailLogId::class, $id);
        $this->assertEquals($uuid, (string) $id);
    }

    public function test_it_generates_new_id(): void
    {
        $id = EmailLogId::generate();

        $this->assertInstanceOf(EmailLogId::class, $id);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            (string) $id
        );
    }

    public function test_it_extends_entity_id(): void
    {
        $id = EmailLogId::generate();

        $this->assertInstanceOf(EntityId::class, $id);
    }

    public function test_it_throws_exception_for_invalid_uuid(): void
    {
        $this->expectException(InvalidArgumentException::class);

        EmailLogId::fromString('not-a-valid-uuid');
    }

    public function test_two_ids_with_same_value_are_equal(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $id1 = EmailLogId::fromString($uuid);
        $id2 = EmailLogId::fromString($uuid);

        $this->assertTrue($id1->equals($id2));
    }

    public function test_two_different_ids_are_not_equal(): void
    {
        $id1 = EmailLogId::generate();
        $id2 = EmailLogId::generate();

        $this->assertFalse($id1->equals($id2));
    }

    public function test_value_property_returns_uuid_string(): void
    {
        $uuid = '550e8400-e29b-41d4-a716-446655440000';

        $id = EmailLogId::fromString($uuid);

        $this->assertEquals($uuid, $id->value);
    }
}
