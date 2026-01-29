<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\ValueObjects;

use App\Domain\Exceptions\InvalidSlugException;
use App\Domain\ValueObjects\Slug;
use PHPUnit\Framework\TestCase;

final class SlugTest extends TestCase
{
    public function test_it_creates_slug_with_valid_value(): void
    {
        $slug = new Slug('valid-slug-123');

        $this->assertEquals('valid-slug-123', $slug->value);
    }

    public function test_it_returns_value_as_string(): void
    {
        $slug = new Slug('my-slug');

        $this->assertEquals('my-slug', (string) $slug);
    }

    public function test_it_throws_exception_for_empty_slug(): void
    {
        $this->expectException(InvalidSlugException::class);

        new Slug('');
    }

    public function test_it_throws_exception_for_invalid_characters(): void
    {
        $this->expectException(InvalidSlugException::class);

        new Slug('invalid slug with spaces');
    }

    public function test_it_throws_exception_for_uppercase_letters(): void
    {
        $this->expectException(InvalidSlugException::class);

        new Slug('Invalid-Uppercase');
    }

    public function test_it_accepts_slug_with_numbers(): void
    {
        $slug = new Slug('event-2024');

        $this->assertEquals('event-2024', $slug->value);
    }

    public function test_it_accepts_slug_with_hyphens(): void
    {
        $slug = new Slug('my-multi-word-slug');

        $this->assertEquals('my-multi-word-slug', $slug->value);
    }

    public function test_it_throws_exception_for_leading_hyphen(): void
    {
        $this->expectException(InvalidSlugException::class);

        new Slug('-invalid-slug');
    }

    public function test_it_throws_exception_for_trailing_hyphen(): void
    {
        $this->expectException(InvalidSlugException::class);

        new Slug('invalid-slug-');
    }

    public function test_it_throws_exception_for_consecutive_hyphens(): void
    {
        $this->expectException(InvalidSlugException::class);

        new Slug('invalid--slug');
    }

    public function test_it_accepts_single_word_slug(): void
    {
        $slug = new Slug('singleword');

        $this->assertEquals('singleword', $slug->value);
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $slug1 = new Slug('my-slug');
        $slug2 = new Slug('my-slug');

        $this->assertTrue($slug1->equals($slug2));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $slug1 = new Slug('first-slug');
        $slug2 = new Slug('second-slug');

        $this->assertFalse($slug1->equals($slug2));
    }

    public function test_from_title_and_uuid_creates_valid_slug(): void
    {
        $uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $slug = Slug::fromTitleAndUuid('My Event Title', $uuid);

        $this->assertEquals('my-event-title-a1b2c3d4', $slug->value);
    }

    public function test_from_title_and_uuid_uses_default_8_characters(): void
    {
        $uuid = 'abcdefgh-1234-5678-90ab-cdef12345678';
        $slug = Slug::fromTitleAndUuid('Test', $uuid);

        $this->assertEquals('test-abcdefgh', $slug->value);
    }

    public function test_from_title_and_uuid_respects_custom_length(): void
    {
        $uuid = 'abcdefgh12345678-90ab-cdef12345678';
        $slug = Slug::fromTitleAndUuid('Test', $uuid, 12);

        $this->assertEquals('test-abcdefgh1234', $slug->value);
    }

    public function test_from_title_and_uuid_handles_empty_title(): void
    {
        $uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $slug = Slug::fromTitleAndUuid('', $uuid);

        $this->assertEquals('item-a1b2c3d4', $slug->value);
    }

    public function test_from_title_and_uuid_handles_special_characters(): void
    {
        $uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $slug = Slug::fromTitleAndUuid('¡Torneo de Warhammer 40K!', $uuid);

        $this->assertEquals('torneo-de-warhammer-40k-a1b2c3d4', $slug->value);
    }

    public function test_from_title_and_uuid_handles_only_special_characters(): void
    {
        $uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        // Str::slug converts !!! to empty but special chars like @ become "at"
        $slug = Slug::fromTitleAndUuid('!!!', $uuid);

        $this->assertEquals('item-a1b2c3d4', $slug->value);
    }

    public function test_from_title_and_uuid_handles_accented_characters(): void
    {
        $uuid = 'a1b2c3d4-e5f6-7890-abcd-ef1234567890';
        $slug = Slug::fromTitleAndUuid('Reunión Ñoño', $uuid);

        $this->assertEquals('reunion-nono-a1b2c3d4', $slug->value);
    }
}
