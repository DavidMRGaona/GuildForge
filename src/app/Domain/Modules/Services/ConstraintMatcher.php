<?php

declare(strict_types=1);

namespace App\Domain\Modules\Services;

use App\Domain\Modules\Exceptions\InvalidVersionConstraintException;
use App\Domain\Modules\ValueObjects\ModuleVersion;

/**
 * Subset of Composer constraints: ^ ~ >= <= > < = and exact versions, joined with
 * "," or spaces (AND) and "||" (OR). A lower or upper bound written without a
 * prerelease stands for "that version with any prerelease" (its floor): 2.6.0-beta
 * satisfies ^2.6 and 3.0.0-rc.1 does not.
 */
final class ConstraintMatcher
{
    /**
     * Components are capped at nine digits so a bound plus one always fits in an int.
     */
    private const string SIMPLE = '/^(>=|<=|>|<|=|\^|~)?(0|[1-9]\d{0,8})(?:\.(0|[1-9]\d{0,8})(?:\.(0|[1-9]\d{0,8})(?:-([0-9A-Za-z]+(?:\.[0-9A-Za-z]+)*))?)?)?$/D';

    /** @var array<string, list<list<array{op: string, bound: ModuleVersion, floor: bool}>>> */
    private array $parsed = [];

    /**
     * @throws InvalidVersionConstraintException
     */
    public function matches(string $constraint, ModuleVersion $version): bool
    {
        foreach ($this->parse($constraint) as $comparisons) {
            if ($this->satisfiesAll($comparisons, $version)) {
                return true;
            }
        }

        return false;
    }

    public function isValid(string $constraint): bool
    {
        try {
            $this->parse($constraint);

            return true;
        } catch (InvalidVersionConstraintException) {
            return false;
        }
    }

    /**
     * @return list<list<array{op: string, bound: ModuleVersion, floor: bool}>>
     */
    private function parse(string $constraint): array
    {
        if (isset($this->parsed[$constraint])) {
            return $this->parsed[$constraint];
        }

        $trimmed = trim($constraint, ' ');

        if ($trimmed === '') {
            throw InvalidVersionConstraintException::invalid($constraint, 'empty constraint');
        }

        $groups = [];

        foreach (preg_split('/ *\|\| */', $trimmed) ?: [] as $group) {
            if ($group === '') {
                throw InvalidVersionConstraintException::invalid($constraint, 'empty alternative around "||"');
            }

            // "operator ws* version": glue the operator to its version before splitting on spaces;
            // only before a digit, so a split operator such as "> = 1.0" stays invalid
            $glued = (string) preg_replace('/(>=|<=|>|<|=|\^|~) +(?=\d)/', '$1', $group);
            $comparisons = [];

            foreach (preg_split('/ *, *| +/', $glued) ?: [] as $simple) {
                array_push($comparisons, ...$this->expand($constraint, $simple));
            }

            $groups[] = $comparisons;
        }

        return $this->parsed[$constraint] = $groups;
    }

    /**
     * @return list<array{op: string, bound: ModuleVersion, floor: bool}>
     */
    private function expand(string $constraint, string $simple): array
    {
        if (preg_match(self::SIMPLE, $simple, $matches) !== 1) {
            throw InvalidVersionConstraintException::invalid($constraint, "unsupported term '{$simple}'");
        }

        $operator = $matches[1];
        $minorText = $matches[3] ?? '';
        $patchText = $matches[4] ?? '';
        $written = $patchText !== '' ? 3 : ($minorText !== '' ? 2 : 1);
        $major = (int) $matches[2];
        $minor = (int) $minorText;
        $patch = (int) $patchText;
        $preRelease = ($matches[5] ?? '') !== '' ? $matches[5] : null;
        $bound = new ModuleVersion($major, $minor, $patch, $preRelease);

        return match ($operator) {
            '^' => [self::atLeast($bound), self::below(self::caretCeiling($major, $minor, $patch, $written))],
            '~' => [
                self::atLeast($bound),
                self::below($written === 3 ? new ModuleVersion($major, $minor + 1, 0) : new ModuleVersion($major + 1, 0, 0)),
            ],
            '>=' => [self::atLeast($bound)],
            '<' => [self::below($bound)],
            '>' => [['op' => '>', 'bound' => $bound, 'floor' => false]],
            '<=' => [['op' => '<=', 'bound' => $bound, 'floor' => false]],
            default => [['op' => '=', 'bound' => $bound, 'floor' => false]],
        };
    }

    /**
     * ^ raises the first non-zero component written; when all are zero, the last one written.
     */
    private static function caretCeiling(int $major, int $minor, int $patch, int $written): ModuleVersion
    {
        if ($major > 0) {
            return new ModuleVersion($major + 1, 0, 0);
        }

        if ($written >= 2 && $minor > 0) {
            return new ModuleVersion(0, $minor + 1, 0);
        }

        if ($written === 3 && $patch > 0) {
            return new ModuleVersion(0, 0, $patch + 1);
        }

        return match ($written) {
            1 => new ModuleVersion(1, 0, 0),
            2 => new ModuleVersion(0, 1, 0),
            default => new ModuleVersion(0, 0, 1),
        };
    }

    /**
     * @return array{op: string, bound: ModuleVersion, floor: bool}
     */
    private static function atLeast(ModuleVersion $bound): array
    {
        return ['op' => '>=', 'bound' => $bound, 'floor' => true];
    }

    /**
     * @return array{op: string, bound: ModuleVersion, floor: bool}
     */
    private static function below(ModuleVersion $bound): array
    {
        return ['op' => '<', 'bound' => $bound, 'floor' => true];
    }

    /**
     * @param  list<array{op: string, bound: ModuleVersion, floor: bool}>  $comparisons
     */
    private function satisfiesAll(array $comparisons, ModuleVersion $version): bool
    {
        foreach ($comparisons as $comparison) {
            $bound = $comparison['bound'];
            // A floor compares only major.minor.patch, so any prerelease of the bound counts as the bound
            $subject = $comparison['floor'] && $bound->preRelease === null
                ? new ModuleVersion($version->major, $version->minor, $version->patch)
                : $version;

            $satisfied = match ($comparison['op']) {
                '>=' => $subject->isGreaterThanOrEqual($bound),
                '<' => $subject->isLessThan($bound),
                '>' => $subject->isGreaterThan($bound),
                '<=' => ! $subject->isGreaterThan($bound),
                default => $subject->isEqualTo($bound),
            };

            if (! $satisfied) {
                return false;
            }
        }

        return true;
    }
}
