<?php

declare(strict_types=1);

namespace Tests\Support\Database;

use Illuminate\Database\Migrations\Migration;
use PHPUnit\Framework\Assert;

/**
 * Runs one migration file by hand. Migration files return an anonymous class whose up() and down()
 * the base Migration class does not declare, so the file and the method are checked before the call.
 */
trait RunsMigrationFiles
{
    protected function runMigrationFile(string $path, string $direction = 'up'): void
    {
        $migration = $this->requireMigration($path);

        if (! method_exists($migration, $direction)) {
            Assert::fail("The migration {$path} has no {$direction}() method");
        }

        $migration->{$direction}();
    }

    protected function requireMigration(string $path): Migration
    {
        $migration = require $path;

        if (! $migration instanceof Migration) {
            Assert::fail("{$path} does not return a migration");
        }

        return $migration;
    }
}
