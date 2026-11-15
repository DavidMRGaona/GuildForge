<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Profiles;

use App\Domain\Profiles\Exceptions\InvalidProfileHandleException;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProfileHandleTest extends TestCase
{
    public function test_it_normalises_case_and_surrounding_spaces(): void
    {
        $handle = ProfileHandle::fromString('  Ana-Garcia ');

        $this->assertSame('ana-garcia', $handle->value);
        $this->assertSame('ana-garcia', (string) $handle);
    }

    public function test_the_shortest_and_the_longest_are_accepted(): void
    {
        $this->assertSame('abc', ProfileHandle::fromString('abc')->value);
        $this->assertSame(str_repeat('a', 30), ProfileHandle::fromString(str_repeat('a', 30))->value);
    }

    #[DataProvider('invalidHandles')]
    public function test_it_refuses_what_is_not_a_handle(string $handle): void
    {
        $this->expectException(InvalidProfileHandleException::class);

        ProfileHandle::fromString($handle);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidHandles(): array
    {
        return [
            'empty' => [''],
            'too short' => ['ab'],
            'too long' => [str_repeat('a', 31)],
            'leading hyphen' => ['-ana'],
            'trailing hyphen' => ['ana-'],
            'double hyphen' => ['ana--garcia'],
            'space' => ['ana garcia'],
            'accent' => ['maría'],
            'underscore' => ['ana_garcia'],
            'dot' => ['ana.garcia'],
            'slash' => ['ana/editar'],
        ];
    }

    #[DataProvider('reservedWords')]
    public function test_it_refuses_the_reserved_words(string $word): void
    {
        $this->assertTrue(ProfileHandle::isReserved($word));
        $this->assertNull(ProfileHandle::tryFrom($word));

        try {
            ProfileHandle::fromString($word);
            $this->fail('A reserved word should not be a handle.');
        } catch (InvalidProfileHandleException $exception) {
            $this->assertTrue($exception->reserved);
        }
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reservedWords(): array
    {
        return [
            'editar' => ['editar'],
            'tarjeta' => ['tarjeta'],
            'admin' => ['admin'],
            'api' => ['api'],
            'upper case' => ['ADMIN'],
        ];
    }

    public function test_a_handle_that_only_contains_a_reserved_word_is_accepted(): void
    {
        $this->assertFalse(ProfileHandle::isReserved('admin-2'));
        $this->assertSame('admin-2', ProfileHandle::fromString('admin-2')->value);
    }

    public function test_try_from_answers_null_instead_of_throwing(): void
    {
        $this->assertNull(ProfileHandle::tryFrom('a'));
        $this->assertSame('ana', ProfileHandle::tryFrom('Ana')?->value);
    }

    public function test_the_hash_is_the_sha256_of_the_normalised_handle(): void
    {
        $this->assertSame(hash('sha256', 'ana-garcia'), ProfileHandle::fromString('ANA-garcia')->hash());
        $this->assertSame(hash('sha256', 'ana-garcia'), ProfileHandle::hashOf(' Ana-Garcia'));
    }

    public function test_two_handles_are_equal_by_value(): void
    {
        $this->assertTrue(ProfileHandle::fromString('ana')->equals(ProfileHandle::fromString('ANA')));
        $this->assertFalse(ProfileHandle::fromString('ana')->equals(ProfileHandle::fromString('bea')));
    }
}
