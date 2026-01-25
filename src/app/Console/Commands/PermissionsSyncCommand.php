<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Infrastructure\Authorization\CorePermissionDefinitions;
use Illuminate\Console\Command;

final class PermissionsSyncCommand extends Command
{
    protected $signature = 'permissions:sync
                            {--dry-run : Show what would be done without making changes}';

    protected $description = 'Sync permission definitions to the database';

    public function __construct(
        private readonly PermissionRegistryInterface $permissionRegistry,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('Syncing permissions to database...');

        // Register core permissions
        $corePermissions = CorePermissionDefinitions::all();
        $this->permissionRegistry->registerMany($corePermissions);

        $allPermissions = $this->permissionRegistry->all();

        if ($this->option('dry-run')) {
            $this->showDryRunOutput($allPermissions);

            return Command::SUCCESS;
        }

        // Sync to database
        $this->permissionRegistry->syncToDatabase();

        $this->info('Permissions synced successfully!');
        $this->newLine();

        // Show summary
        $grouped = $this->permissionRegistry->grouped();
        $this->table(
            ['Resource', 'Permissions'],
            collect($grouped)->map(fn ($perms, $resource) => [
                $resource,
                count($perms),
            ])->toArray()
        );

        $this->newLine();
        $this->info('Total permissions: '.count($allPermissions));

        return Command::SUCCESS;
    }

    /**
     * @param  array<\App\Application\Authorization\DTOs\PermissionDefinitionDTO>  $permissions
     */
    private function showDryRunOutput(array $permissions): void
    {
        $this->warn('DRY RUN - No changes made');
        $this->newLine();

        $this->info('Permissions that would be synced:');
        $this->newLine();

        $rows = [];
        foreach ($permissions as $permission) {
            $rows[] = [
                $permission->key,
                $permission->resource,
                $permission->action,
                $permission->module ?? 'core',
                implode(', ', $permission->defaultRoles) ?: '-',
            ];
        }

        $this->table(
            ['Key', 'Resource', 'Action', 'Module', 'Default roles'],
            $rows
        );

        $this->newLine();
        $this->info('Total permissions: '.count($permissions));
    }
}
