<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Profiles;

use App\Domain\Profiles\ValueObjects\ProfileKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ProfileKeyTest extends TestCase
{
    public function test_a_module_key_has_its_owner_and_name(): void
    {
        $key = ProfileKey::tryFrom('game-tables.tables_run');

        $this->assertInstanceOf(ProfileKey::class, $key);
        $this->assertSame('game-tables.tables_run', $key->value);
        $this->assertSame('game-tables', $key->owner());
        $this->assertFalse($key->isCore());
    }

    public function test_a_core_key_is_the_core_s(): void
    {
        $this->assertTrue(ProfileKey::tryFrom('core.about')?->isCore());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidKeys(): iterable
    {
        yield 'no owner' => ['about'];
        yield 'upper case' => ['Forum.posts'];
        yield 'two dots' => ['forum.latest.posts'];
        yield 'empty name' => ['forum.'];
        yield 'spaces' => ['forum. posts'];
        yield 'too long' => ['forum.'.str_repeat('a', 100)];
    }

    #[DataProvider('invalidKeys')]
    public function test_an_invalid_key_is_refused(string $key): void
    {
        $this->assertNull(ProfileKey::tryFrom($key));
    }
}
