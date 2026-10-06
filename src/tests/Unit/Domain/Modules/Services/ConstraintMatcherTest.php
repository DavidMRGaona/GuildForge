<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Modules\Services;

use App\Domain\Modules\Exceptions\InvalidVersionConstraintException;
use App\Domain\Modules\Services\ConstraintMatcher;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConstraintMatcherTest extends TestCase
{
    /**
     * @return iterable<string, array{0: string, 1: string, 2: bool}>
     */
    public static function cases(): iterable
    {
        // Caret
        yield '^1.2.3 lower' => ['^1.2.3', '1.2.3', true];
        yield '^1.2.3 below lower' => ['^1.2.3', '1.2.2', false];
        yield '^1.2.3 top of major' => ['^1.2.3', '1.99.0', true];
        yield '^1.2.3 next major' => ['^1.2.3', '2.0.0', false];
        yield '^1.2 keeps the patch out of the floor' => ['^1.2', '1.2.0', true];
        yield '^1.2 below' => ['^1.2', '1.1.9', false];
        yield '^1 whole major' => ['^1', '1.0.0', true];
        yield '^0.3.1 lower' => ['^0.3.1', '0.3.1', true];
        yield '^0.3.1 next minor' => ['^0.3.1', '0.4.0', false];
        yield '^0.3 next minor' => ['^0.3', '0.4.0', false];
        yield '^0.3 inside' => ['^0.3', '0.3.9', true];
        yield '^0.0.3 exact patch' => ['^0.0.3', '0.0.3', true];
        yield '^0.0.3 next patch' => ['^0.0.3', '0.0.4', false];
        yield '^0.0 inside' => ['^0.0', '0.0.9', true];
        yield '^0.0 next minor' => ['^0.0', '0.1.0', false];
        yield '^0 inside' => ['^0', '0.9.9', true];
        yield '^0 next major' => ['^0', '1.0.0', false];
        // Prerelease boundaries
        yield 'core 3 rc does not satisfy ^2.6' => ['^2.6', '3.0.0-rc.1', false];
        yield 'core 2.6 beta satisfies ^2.6' => ['^2.6', '2.6.0-beta', true];
        yield 'core 2.6.0 satisfies ^2.6' => ['^2.6', '2.6.0', true];
        yield 'core 2.5.10 does not satisfy ^2.6' => ['^2.6', '2.5.10', false];
        yield 'default ^2.0 on 2.6.0' => ['^2.0', '2.6.0', true];
        yield 'default ^2.0 on 3.0.0' => ['^2.0', '3.0.0', false];
        yield 'explicit prerelease lower bound' => ['>=2.6.0-beta.2', '2.6.0-beta.10', true];
        yield 'explicit prerelease lower bound below' => ['>=2.6.0-beta.2', '2.6.0-beta.1', false];
        // Tilde (Composer semantics)
        yield '~1.2.3 inside' => ['~1.2.3', '1.2.9', true];
        yield '~1.2.3 next minor' => ['~1.2.3', '1.3.0', false];
        yield '~1.2 next minor is inside' => ['~1.2', '1.3.0', true];
        yield '~1.2 next major' => ['~1.2', '2.0.0', false];
        yield '~1 inside' => ['~1', '1.5.0', true];
        // Comparison operators
        yield '>=1.2 partial' => ['>=1.2', '1.2.0', true];
        yield '>=1.2 floor accepts prerelease' => ['>=1.2', '1.2.0-alpha', true];
        yield '>=8.2 php' => ['>=8.2', '8.4.26', true];
        yield '<2.0 excludes 2.0 prerelease' => ['<2.0', '2.0.0-beta', false];
        yield '<2.0 inside' => ['<2.0', '1.99.99', true];
        yield '>1.2 equal' => ['>1.2', '1.2.0', false];
        yield '>1.2 above' => ['>1.2', '1.2.1', true];
        yield '<=1.2 equal' => ['<=1.2', '1.2.0', true];
        yield '<=1.2 above' => ['<=1.2', '1.2.1', false];
        yield '<=1.2 prerelease below' => ['<=1.2', '1.2.0-beta', true];
        // Exact
        yield 'exact' => ['1.2.3', '1.2.3', true];
        yield 'exact rejects prerelease' => ['1.2.3', '1.2.3-beta', false];
        yield '=1.2 completes zeros' => ['=1.2', '1.2.0', true];
        yield '=1.2.3 other' => ['=1.2.3', '1.2.4', false];
        // AND / OR and whitespace
        yield 'and with space' => ['>=1.0 <2.0', '1.5.0', true];
        yield 'and with space outside' => ['>=1.0 <2.0', '2.0.0', false];
        yield 'and with comma' => ['>=1.0,<2.0', '1.5.0', true];
        yield 'and with comma and spaces' => ['>=1.0 , <2.0', '0.9.0', false];
        yield 'or first' => ['^3.2 || ^4.0', '3.5.0', true];
        yield 'or second (the old cast read this as ^3.2)' => ['^3.2 || ^4.0', '4.1.0', true];
        yield 'or none' => ['^3.2 || ^4.0', '5.0.0', false];
        yield 'or without spaces' => ['^3.2||^4.0', '4.0.0', true];
        yield 'space after operator' => ['>= 1.2', '1.2.0', true];
        yield 'surrounding spaces' => [' ^2.6 ', '2.7.0', true];
        // Boundaries pinned during review
        yield 'and range excludes the upper bound prerelease' => ['>=1.0 <2.0', '2.0.0-beta', false];
        yield '^0.0.0 exact zero' => ['^0.0.0', '0.0.0', true];
        yield '^0.0.0 next patch' => ['^0.0.0', '0.0.1', false];
        yield '^1.2.3-beta lower prerelease' => ['^1.2.3-beta', '1.2.3-alpha', false];
        yield '^1.2.3-beta higher prerelease' => ['^1.2.3-beta', '1.2.3-beta.1', true];
        yield '>1.2 next patch prerelease' => ['>1.2', '1.2.1-beta', true];
        yield 'or exact first' => ['1.2.3 || ^2.0', '1.2.3', true];
        yield 'or caret second' => ['1.2.3 || ^2.0', '2.5.0', true];
        yield '~1.2.3 below' => ['~1.2.3', '1.2.2', false];
    }

    #[DataProvider('cases')]
    public function test_matches(string $constraint, string $version, bool $expected): void
    {
        $this->assertSame($expected, (new ConstraintMatcher())->matches($constraint, ModuleVersion::fromString($version)));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function validConstraints(): iterable
    {
        foreach (['^2.6', '^2.6.0', '~1.2', '>=8.2', '>=12.0', '<3.0', '>1.2', '<=1.2', '=1.2.3', '1.2.3', '>=1.0 <2.0',
            '>=1.0,<2.0', '>=1.0, <2.0', '^2.6 || ^3.0', '^3.2||^4.0', '>= 8.2', ' ^2.6 ', '2.6.0-beta.1', '^1.2.3-beta', '0', '^0.0'] as $constraint) {
            yield "'{$constraint}'" => [$constraint];
        }
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function invalidConstraints(): iterable
    {
        foreach (['', ' ', '*', '2.x', '2.*', 'v2.6', '^2.6-beta', '2.6-beta', '!=2.0', '1.0 - 2.0', '^2.6@beta', '^02.1',
            '^2.6 ||', '|| ^2.6', '^2.6,', '>=', '^2.6.0.1', '>= 1.0 <', 'latest', '^2.6 | ^3.0',
            '^9223372036854775807', '99999999999999999999', "^2.6\n", '> = 1.0', '< = 2.0'] as $constraint) {
            yield "'{$constraint}'" => [$constraint];
        }
    }

    #[DataProvider('validConstraints')]
    public function test_valid_constraints(string $constraint): void
    {
        $this->assertTrue((new ConstraintMatcher())->isValid($constraint));
    }

    #[DataProvider('invalidConstraints')]
    public function test_invalid_constraints(string $constraint): void
    {
        $this->assertFalse((new ConstraintMatcher())->isValid($constraint));
    }

    public function test_matching_an_invalid_constraint_throws(): void
    {
        $this->expectException(InvalidVersionConstraintException::class);
        $this->expectExceptionMessage("Invalid version constraint '2.x'");

        (new ConstraintMatcher())->matches('2.x', ModuleVersion::fromString('2.0.0'));
    }
}
