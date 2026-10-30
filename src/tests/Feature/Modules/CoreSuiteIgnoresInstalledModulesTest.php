<?php

declare(strict_types=1);

namespace Tests\Feature\Modules;

use Illuminate\Database\Migrations\Migrator;
use Tests\TestCase;

/**
 * Modules checked out in src/modules (separate repos, absent in CI) must not reach the core
 * suite: their migrations would run on its database and, for instance, grant module
 * permissions to core roles. Tests that need a module point modules.path at a fixture.
 */
final class CoreSuiteIgnoresInstalledModulesTest extends TestCase
{
    public function test_the_modules_path_is_not_the_installed_modules_directory(): void
    {
        $this->assertNotSame(
            rtrim(base_path('modules'), '/'),
            rtrim((string) config('modules.path'), '/'),
        );
    }

    public function test_no_migration_of_an_installed_module_is_registered(): void
    {
        $installed = rtrim(base_path('modules'), '/').'/';

        $leaked = array_filter(
            $this->app->make(Migrator::class)->paths(),
            static fn (string $path): bool => str_starts_with($path, $installed),
        );

        $this->assertSame([], array_values($leaked));
    }
}
