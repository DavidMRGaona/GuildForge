<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Authorization\ValueObjects;

use App\Domain\Authorization\ValueObjects\RoleName;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class RoleNameTest extends TestCase
{
    public function test_it_creates_with_valid_kebab_case_name(): void
    {
        $name = new RoleName('admin');

        $this->assertEquals('admin', $name->value);
    }

    public function test_it_creates_with_multi_word_kebab_case(): void
    {
        $name = new RoleName('content-editor');

        $this->assertEquals('content-editor', $name->value);
    }

    public function test_it_creates_with_complex_kebab_case(): void
    {
        $name = new RoleName('super-admin');

        $this->assertEquals('super-admin', $name->value);
    }

    public function test_it_accepts_minimum_2_characters(): void
    {
        $name = new RoleName('ab');

        $this->assertEquals('ab', $name->value);
    }

    public function test_it_accepts_maximum_50_characters(): void
    {
        $value = str_repeat('a', 50);
        $name = new RoleName($value);

        $this->assertEquals($value, $name->value);
    }

    public function test_it_throws_exception_for_empty_string(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Role name cannot be empty');

        new RoleName('');
    }

    public function test_it_throws_exception_for_too_short_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Role name must be between 2 and 50 characters');

        new RoleName('a');
    }

    public function test_it_throws_exception_for_too_long_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Role name must be between 2 and 50 characters');

        new RoleName(str_repeat('a', 51));
    }

    public function test_it_throws_exception_for_uppercase_letters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Role name must be in kebab-case format');

        new RoleName('Admin');
    }

    public function test_it_throws_exception_for_spaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Role name must be in kebab-case format');

        new RoleName('super admin');
    }

    public function test_it_throws_exception_for_underscores(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Role name must be in kebab-case format');

        new RoleName('content_editor');
    }

    public function test_it_throws_exception_for_camel_case(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Role name must be in kebab-case format');

        new RoleName('contentEditor');
    }

    public function test_it_returns_value_as_string(): void
    {
        $name = new RoleName('admin');

        $this->assertEquals('admin', (string) $name);
    }

    public function test_equals_returns_true_for_same_value(): void
    {
        $name1 = new RoleName('admin');
        $name2 = new RoleName('admin');

        $this->assertTrue($name1->equals($name2));
    }

    public function test_equals_returns_false_for_different_values(): void
    {
        $name1 = new RoleName('admin');
        $name2 = new RoleName('editor');

        $this->assertFalse($name1->equals($name2));
    }
}
