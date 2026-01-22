<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\ValueObjects;

use App\Domain\Modules\Exceptions\InvalidModuleNameException;
use App\Domain\Modules\ValueObjects\ModuleName;
use PHPUnit\Framework\TestCase;

final class ModuleNameTest extends TestCase
{
    public function test_it_creates_module_name_with_valid_kebab_case_single_word(): void
    {
        $name = new ModuleName('forum');

        $this->assertEquals('forum', $name->value);
    }

    public function test_it_creates_module_name_with_valid_kebab_case_multi_word(): void
    {
        $name = new ModuleName('game-library');

        $this->assertEquals('game-library', $name->value);
    }

    public function test_it_returns_value_as_string(): void
    {
        $name = new ModuleName('forum');

        $this->assertEquals('forum', (string) $name);
    }

    public function test_it_throws_exception_for_empty_name(): void
    {
        $this->expectException(InvalidModuleNameException::class);

        new ModuleName('');
    }

    public function test_it_throws_exception_for_uppercase_letters(): void
    {
        $this->expectException(InvalidModuleNameException::class);

        new ModuleName('GameLibrary');
    }

    public function test_it_throws_exception_for_spaces(): void
    {
        $this->expectException(InvalidModuleNameException::class);

        new ModuleName('game library');
    }

    public function test_it_throws_exception_for_leading_hyphen(): void
    {
        $this->expectException(InvalidModuleNameException::class);

        new ModuleName('-forum');
    }

    public function test_it_throws_exception_for_trailing_hyphen(): void
    {
        $this->expectException(InvalidModuleNameException::class);

        new ModuleName('forum-');
    }

    public function test_it_throws_exception_for_consecutive_hyphens(): void
    {
        $this->expectException(InvalidModuleNameException::class);

        new ModuleName('game--library');
    }

    public function test_it_throws_exception_for_starting_with_number(): void
    {
        $this->expectException(InvalidModuleNameException::class);

        new ModuleName('1forum');
    }

    public function test_it_accepts_single_word_name(): void
    {
        $name = new ModuleName('shop');

        $this->assertEquals('shop', $name->value);
    }

    public function test_it_accepts_multi_word_kebab_case_name(): void
    {
        $name = new ModuleName('event-calendar');

        $this->assertEquals('event-calendar', $name->value);
    }

    public function test_to_studly_case_converts_game_library_to_game_library(): void
    {
        $name = new ModuleName('game-library');

        $this->assertEquals('GameLibrary', $name->toStudlyCase());
    }

    public function test_to_studly_case_converts_forum_to_forum(): void
    {
        $name = new ModuleName('forum');

        $this->assertEquals('Forum', $name->toStudlyCase());
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $name1 = new ModuleName('forum');
        $name2 = new ModuleName('forum');

        $this->assertTrue($name1->equals($name2));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $name1 = new ModuleName('forum');
        $name2 = new ModuleName('shop');

        $this->assertFalse($name1->equals($name2));
    }
}
