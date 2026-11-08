<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Modules\DTOs\PermissionDTO;
use App\Application\Modules\Services\EnabledModulesResolverInterface;
use App\Domain\Authorization\Exceptions\InvalidPermissionKeyException;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Modules\ModuleLoader;
use App\Modules\ModuleServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * permissions:sync runs on every deploy: it is how new core permissions reach existing sites.
 */
final class PermissionsSyncCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sync_creates_the_menu_item_permissions(): void
    {
        $this->artisanCommand('permissions:sync')->assertSuccessful();

        $this->assertSame(5, PermissionModel::query()->whereIn('key', [
            'menu_items.view_any',
            'menu_items.view',
            'menu_items.create',
            'menu_items.update',
            'menu_items.delete',
        ])->where('resource', 'menu_items')->count());
    }

    /**
     * The sync only creates and updates permissions: one no longer registered (a retired
     * permission, a module's after it is disabled or uninstalled) keeps its row and stays on the
     * roles that hold it. That is why it writes nothing to the role history; a sync that deleted
     * permissions would have to log what each role loses before the cascade.
     */
    public function test_sync_keeps_a_permission_no_longer_registered_on_the_roles_that_hold_it(): void
    {
        $retired = PermissionModel::query()->create([
            'key' => 'retired_items.view_any',
            'label' => 'Ver elementos retirados',
            'resource' => 'retired_items',
            'action' => 'view_any',
            'module' => 'retired-module',
        ]);
        $role = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta']);
        $role->permissions()->attach($retired->id);

        $this->artisanCommand('permissions:sync')->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['key' => 'retired_items.view_any']);
        $this->assertDatabaseHas('role_permission', ['role_id' => $role->id, 'permission_id' => $retired->id]);
        $this->assertDatabaseEmpty('role_audit_log');
    }

    /**
     * One module permission outside the "resource.action" form used to stop the whole sync, core
     * and other modules included (core#402). In production it is now dropped with a warning.
     */
    public function test_a_module_permission_without_resource_action_does_not_stop_the_sync_in_production(): void
    {
        $log = Log::spy();
        $this->app->detectEnvironment(fn (): string => 'production');
        $this->app->forgetInstance(PermissionRegistryInterface::class);

        $this->registerModulePermissions(new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerPermissions(): array
            {
                return [
                    new PermissionDTO(name: 'manage', label: 'Gestionar', group: 'Ejemplo', module: 'example-module'),
                    new PermissionDTO(name: 'settings.manage', label: 'Ajustes', group: 'Ejemplo', module: 'example-module'),
                ];
            }
        });

        $this->artisanCommand('permissions:sync')->assertSuccessful();

        $this->assertDatabaseHas('permissions', ['key' => 'example-module:settings.manage']);
        $this->assertDatabaseHas('permissions', ['key' => 'menu_items.view_any']);
        $this->assertDatabaseMissing('permissions', ['key' => 'example-module:manage']);
        $log->shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => str_contains($message, '"example-module"')
                && $context['key'] === 'example-module:manage',
        );
    }

    /**
     * Locally, in tests and in a module's CI the same permission fails as soon as the module
     * loads, so its author sees it before it reaches a site.
     */
    public function test_a_module_permission_without_resource_action_fails_as_the_module_loads_in_tests(): void
    {
        $this->expectException(InvalidPermissionKeyException::class);
        $this->expectExceptionMessage('"example-module:manage"');

        $this->registerModulePermissions(new class($this->app) extends ModuleServiceProvider
        {
            public function moduleName(): string
            {
                return 'example-module';
            }

            public function registerPermissions(): array
            {
                return [new PermissionDTO(name: 'manage', label: 'Gestionar', group: 'Ejemplo', module: 'example-module')];
            }
        });
    }

    private function registerModulePermissions(ModuleServiceProvider $provider): void
    {
        $loader = new ModuleLoader($this->app, Mockery::mock(EnabledModulesResolverInterface::class));

        (new ReflectionMethod($loader, 'registerProviderPermissions'))->invoke($loader, $provider);
    }
}
