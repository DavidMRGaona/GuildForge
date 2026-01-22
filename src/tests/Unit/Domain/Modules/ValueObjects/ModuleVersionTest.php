<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\ValueObjects;

use App\Domain\Modules\Exceptions\InvalidModuleVersionException;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use PHPUnit\Framework\TestCase;

final class ModuleVersionTest extends TestCase
{
    public function test_it_creates_module_version_from_major_minor_patch_integers(): void
    {
        $version = new ModuleVersion(1, 2, 3);

        $this->assertEquals('1.2.3', $version->value());
    }

    public function test_it_creates_module_version_from_string(): void
    {
        $version = ModuleVersion::fromString('1.2.3');

        $this->assertEquals('1.2.3', $version->value());
    }

    public function test_it_throws_exception_for_invalid_format(): void
    {
        $this->expectException(InvalidModuleVersionException::class);

        ModuleVersion::fromString('invalid');
    }

    public function test_it_throws_exception_for_incomplete_version(): void
    {
        $this->expectException(InvalidModuleVersionException::class);

        ModuleVersion::fromString('1.2');
    }

    public function test_value_returns_correct_format(): void
    {
        $version = new ModuleVersion(2, 5, 10);

        $this->assertEquals('2.5.10', $version->value());
    }

    public function test_is_greater_than_compares_correctly(): void
    {
        $version1 = new ModuleVersion(2, 0, 0);
        $version2 = new ModuleVersion(1, 9, 9);

        $this->assertTrue($version1->isGreaterThan($version2));
        $this->assertFalse($version2->isGreaterThan($version1));
    }

    public function test_is_greater_than_or_equal_compares_correctly(): void
    {
        $version1 = new ModuleVersion(1, 5, 0);
        $version2 = new ModuleVersion(1, 5, 0);
        $version3 = new ModuleVersion(1, 4, 0);

        $this->assertTrue($version1->isGreaterThanOrEqual($version2));
        $this->assertTrue($version1->isGreaterThanOrEqual($version3));
        $this->assertFalse($version3->isGreaterThanOrEqual($version1));
    }

    public function test_is_less_than_compares_correctly(): void
    {
        $version1 = new ModuleVersion(1, 0, 0);
        $version2 = new ModuleVersion(2, 0, 0);

        $this->assertTrue($version1->isLessThan($version2));
        $this->assertFalse($version2->isLessThan($version1));
    }

    public function test_is_equal_to_compares_correctly(): void
    {
        $version1 = new ModuleVersion(1, 2, 3);
        $version2 = new ModuleVersion(1, 2, 3);
        $version3 = new ModuleVersion(1, 2, 4);

        $this->assertTrue($version1->isEqualTo($version2));
        $this->assertFalse($version1->isEqualTo($version3));
    }

    public function test_satisfies_caret_constraint_returns_true_for_compatible_version(): void
    {
        $version = new ModuleVersion(1, 5, 0);

        $this->assertTrue($version->satisfies('^1.0'));
    }

    public function test_satisfies_caret_constraint_returns_false_for_major_version_change(): void
    {
        $version = new ModuleVersion(2, 0, 0);

        $this->assertFalse($version->satisfies('^1.0'));
    }

    public function test_satisfies_caret_one_dot_two_returns_true_for_one_dot_two_dot_three(): void
    {
        $version = new ModuleVersion(1, 2, 3);

        $this->assertTrue($version->satisfies('^1.2'));
    }

    public function test_satisfies_caret_one_dot_two_returns_true_for_one_dot_nine_dot_nine(): void
    {
        $version = new ModuleVersion(1, 9, 9);

        $this->assertTrue($version->satisfies('^1.2'));
    }

    public function test_satisfies_caret_one_dot_two_returns_false_for_one_dot_one_dot_zero(): void
    {
        $version = new ModuleVersion(1, 1, 0);

        $this->assertFalse($version->satisfies('^1.2'));
    }

    public function test_satisfies_greater_than_or_equal_returns_true_for_exact_match(): void
    {
        $version = new ModuleVersion(1, 2, 0);

        $this->assertTrue($version->satisfies('>=1.2.0'));
    }

    public function test_satisfies_greater_than_or_equal_returns_true_for_higher_version(): void
    {
        $version = new ModuleVersion(1, 3, 0);

        $this->assertTrue($version->satisfies('>=1.2.0'));
    }

    public function test_satisfies_greater_than_or_equal_returns_true_for_major_version_bump(): void
    {
        $version = new ModuleVersion(2, 0, 0);

        $this->assertTrue($version->satisfies('>=1.2.0'));
    }

    public function test_satisfies_greater_than_or_equal_returns_false_for_lower_version(): void
    {
        $version = new ModuleVersion(1, 1, 9);

        $this->assertFalse($version->satisfies('>=1.2.0'));
    }

    public function test_satisfies_tilde_constraint_returns_true_for_one_dot_two_dot_zero(): void
    {
        $version = new ModuleVersion(1, 2, 0);

        $this->assertTrue($version->satisfies('~1.2'));
    }

    public function test_satisfies_tilde_constraint_returns_true_for_one_dot_two_dot_nine(): void
    {
        $version = new ModuleVersion(1, 2, 9);

        $this->assertTrue($version->satisfies('~1.2'));
    }

    public function test_satisfies_tilde_constraint_returns_false_for_one_dot_three_dot_zero(): void
    {
        $version = new ModuleVersion(1, 3, 0);

        $this->assertFalse($version->satisfies('~1.2'));
    }

    public function test_satisfies_exact_version_returns_true_only_for_exact_match(): void
    {
        $version1 = new ModuleVersion(1, 0, 0);
        $version2 = new ModuleVersion(1, 0, 1);

        $this->assertTrue($version1->satisfies('1.0.0'));
        $this->assertFalse($version2->satisfies('1.0.0'));
    }

    public function test_it_returns_string_representation(): void
    {
        $version = new ModuleVersion(3, 14, 159);

        $this->assertEquals('3.14.159', (string) $version);
    }
}
