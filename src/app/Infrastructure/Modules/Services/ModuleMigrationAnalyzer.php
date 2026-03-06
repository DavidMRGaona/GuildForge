<?php

declare(strict_types=1);

namespace App\Infrastructure\Modules\Services;

use App\Application\Modules\Services\ModuleMigrationAnalyzerInterface;
use App\Domain\Modules\Exceptions\ModuleMigrationViolationException;
use App\Domain\Modules\ValueObjects\CoreTableRegistry;

final readonly class ModuleMigrationAnalyzer implements ModuleMigrationAnalyzerInterface
{
    public function __construct(
        private CoreTableRegistry $registry,
    ) {}

    public function analyzeMigrations(string $moduleName, string $migrationsPath): void
    {
        if (! is_dir($migrationsPath)) {
            return;
        }

        $files = glob($migrationsPath.'/*.php');

        if ($files === false || $files === []) {
            return;
        }

        $allowedPrefixes = ModuleSchemaGuard::derivePrefixes($moduleName);

        foreach ($files as $file) {
            $content = file_get_contents($file);

            if ($content === false) {
                continue;
            }

            $violations = $this->detectMigrationViolations($content, $allowedPrefixes);

            if ($violations !== []) {
                throw ModuleMigrationViolationException::withViolations(
                    $moduleName,
                    basename($file),
                    $violations,
                );
            }
        }
    }

    public function analyzeSeeders(string $moduleName, string $seedersPath): void
    {
        if (! is_dir($seedersPath)) {
            return;
        }

        $files = glob($seedersPath.'/*.php');

        if ($files === false || $files === []) {
            return;
        }

        foreach ($files as $file) {
            $content = file_get_contents($file);

            if ($content === false) {
                continue;
            }

            $violations = $this->detectSeederViolations($content);

            if ($violations !== []) {
                throw ModuleMigrationViolationException::withViolations(
                    $moduleName,
                    basename($file),
                    $violations,
                );
            }
        }
    }

    /**
     * @param  array<int, string>  $allowedPrefixes
     * @return array<int, string>
     */
    private function detectMigrationViolations(string $content, array $allowedPrefixes): array
    {
        $violations = [];

        // Detect Schema:: calls with explicit table names
        $schemaPattern = '/Schema\s*::\s*(create|table|drop|dropIfExists|rename)\s*\(\s*([\'"])([a-zA-Z_][a-zA-Z0-9_]*)\2/';
        if (preg_match_all($schemaPattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $operation = $match[1];
                $tableName = $match[3];

                if ($this->registry->isCore($tableName)) {
                    $violations[] = "Schema::{$operation}('{$tableName}') targets a core table";
                } elseif (! $this->hasAllowedPrefix($tableName, $allowedPrefixes)) {
                    $violations[] = "Schema::{$operation}('{$tableName}') targets a table not owned by this module";
                }
            }
        }

        // Detect Schema::rename with second argument being a core table
        $renamePattern = '/Schema\s*::\s*rename\s*\(\s*[\'"][a-zA-Z_][a-zA-Z0-9_]*[\'"]\s*,\s*([\'"])([a-zA-Z_][a-zA-Z0-9_]*)\1/';
        if (preg_match_all($renamePattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tableName = $match[2];

                if ($this->registry->isCore($tableName)) {
                    $violations[] = "Schema::rename() targets core table '{$tableName}' as destination";
                }
            }
        }

        // Detect dynamic table names in Schema:: calls (variables instead of strings)
        $dynamicSchemaPattern = '/Schema\s*::\s*(create|table|drop|dropIfExists|rename)\s*\(\s*\$/';
        if (preg_match_all($dynamicSchemaPattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $violations[] = "Schema::{$match[1]}() uses a dynamic table name (cannot be analyzed statically)";
            }
        }

        // Detect raw SQL DDL on core tables
        $rawDdlPattern = '/DB\s*::\s*statement\s*\(\s*([\'"])((?:ALTER\s+TABLE|DROP\s+TABLE|CREATE\s+TABLE|TRUNCATE\s+TABLE?)\s+(?:IF\s+(?:NOT\s+)?EXISTS\s+)?([a-zA-Z_][a-zA-Z0-9_]*))/i';
        if (preg_match_all($rawDdlPattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tableName = $match[3];

                if ($this->registry->isCore($tableName)) {
                    $violations[] = "DB::statement() contains DDL targeting core table '{$tableName}'";
                }
            }
        }

        // Detect dynamic DB::statement (variable instead of string)
        $dynamicDbPattern = '/DB\s*::\s*statement\s*\(\s*\$/';
        if (preg_match($dynamicDbPattern, $content)) {
            $violations[] = 'DB::statement() uses a dynamic query (cannot be analyzed statically)';
        }

        return $violations;
    }

    /**
     * @return array<int, string>
     */
    private function detectSeederViolations(string $content): array
    {
        $violations = [];

        // Seeders should not use Schema:: at all
        if (preg_match('/Schema\s*::/', $content)) {
            $violations[] = 'Seeders must not use Schema:: operations';
        }

        // Detect DB::table('core_table')->delete() or ->truncate()
        $destructiveDmlPattern = '/DB\s*::\s*table\s*\(\s*([\'"])([a-zA-Z_][a-zA-Z0-9_]*)\1\s*\)\s*->\s*(delete|truncate)\s*\(/';
        if (preg_match_all($destructiveDmlPattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tableName = $match[2];
                $operation = $match[3];

                if ($this->registry->isCore($tableName)) {
                    $violations[] = "DB::table('{$tableName}')->{$operation}() targets a core table";
                }
            }
        }

        // Detect raw SQL DELETE/TRUNCATE on core tables
        $rawDmlPattern = '/DB\s*::\s*statement\s*\(\s*([\'"])((?:DELETE\s+FROM|TRUNCATE\s+(?:TABLE\s+)?)\s*([a-zA-Z_][a-zA-Z0-9_]*))/i';
        if (preg_match_all($rawDmlPattern, $content, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $tableName = $match[3];

                if ($this->registry->isCore($tableName)) {
                    $violations[] = "DB::statement() contains destructive DML targeting core table '{$tableName}'";
                }
            }
        }

        return $violations;
    }

    /**
     * @param  array<int, string>  $allowedPrefixes
     */
    private function hasAllowedPrefix(string $tableName, array $allowedPrefixes): bool
    {
        foreach ($allowedPrefixes as $prefix) {
            if (str_starts_with($tableName, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
