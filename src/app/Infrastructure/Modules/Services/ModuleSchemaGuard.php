<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Domain\Modules\Exceptions\ModuleMigrationViolationException;
use App\Domain\Modules\ValueObjects\CoreTableRegistry;
use Closure;

final class ModuleSchemaGuard
{
    private bool $active = false;

    private string $currentModuleName = '';

    /** @var array<int, string> */
    private array $allowedPrefixes = [];

    public function __construct(
        private readonly CoreTableRegistry $registry,
    ) {}

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public function protect(string $moduleName, Closure $callback): mixed
    {
        $this->active = true;
        $this->currentModuleName = $moduleName;
        $this->allowedPrefixes = self::derivePrefixes($moduleName);

        try {
            return $callback();
        } finally {
            $this->active = false;
            $this->currentModuleName = '';
            $this->allowedPrefixes = [];
        }
    }

    public function assertPermitted(string $operation, string $tableName): void
    {
        if (! $this->active) {
            return;
        }

        if ($this->registry->isCore($tableName)) {
            throw ModuleMigrationViolationException::withViolations(
                $this->currentModuleName,
                'runtime',
                ["{$operation} on core table '{$tableName}' is not permitted"],
            );
        }

        if (! $this->hasAllowedPrefix($tableName)) {
            throw ModuleMigrationViolationException::withViolations(
                $this->currentModuleName,
                'runtime',
                ["{$operation} on table '{$tableName}' is not permitted (not owned by module '{$this->currentModuleName}')"],
            );
        }
    }

    private function hasAllowedPrefix(string $tableName): bool
    {
        foreach ($this->allowedPrefixes as $prefix) {
            if (str_starts_with($tableName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, string>
     */
    public static function derivePrefixes(string $moduleName): array
    {
        $withoutHyphens = str_replace('-', '', $moduleName);
        $withUnderscores = str_replace('-', '_', $moduleName);

        $prefixes = [$withoutHyphens.'_'];

        if ($withUnderscores !== $withoutHyphens) {
            $prefixes[] = $withUnderscores.'_';
        }

        return $prefixes;
    }
}
