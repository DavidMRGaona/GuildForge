<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Roles;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\PermissionRegistryInterface;
use App\Application\Modules\Services\ModuleManagerServiceInterface;
use App\Domain\Modules\Collections\ModuleCollection;
use App\Domain\Modules\Entities\Module;
use App\Domain\Modules\Enums\ModuleStatus;
use App\Domain\Modules\ValueObjects\ModuleId;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Domain\Modules\ValueObjects\ModuleRequirements;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The core and the modules suggest roles for their permissions. The role editor points them
 * out, but only an admin grants them: a suggestion never ticks a permission on its own.
 */
final class SuggestedPermissionsTest extends TestCase
{
    use LazilyRefreshDatabase;

    private PermissionModel $suggested;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
        $this->suggested = $this->permission('test-module:widgets.view', ['editor']);
        $this->permission('test-module:widgets.delete', []);
        $this->enableTestModule();
    }

    public function test_the_editor_points_out_the_permissions_suggested_for_the_role(): void
    {
        Livewire::test(EditRole::class, ['record' => $this->role('editor')->getRouteKey()])
            ->assertSee('Widgets view: description · Sugerido para este rol')
            ->assertSee('Widgets delete: description')
            ->assertDontSee('Widgets delete: description · Sugerido para este rol');
    }

    public function test_a_permission_suggested_for_another_role_is_not_pointed_out(): void
    {
        Livewire::test(EditRole::class, ['record' => $this->role('socio')->getRouteKey()])
            ->assertSee('Widgets view: description')
            ->assertDontSee('Widgets view: description · Sugerido para este rol');
    }

    public function test_a_new_role_has_no_suggestions(): void
    {
        Livewire::test(CreateRole::class)
            ->assertSee('Widgets view: description')
            ->assertDontSee('· Sugerido para este rol');
    }

    public function test_a_suggestion_is_never_granted_on_its_own(): void
    {
        $editor = $this->role('editor');

        Livewire::test(EditRole::class, ['record' => $editor->getRouteKey()])
            ->assertSchemaStateSet(['permissions_by_section.module-test-module.widgets' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertFalse($editor->permissions()->whereKey($this->suggested->id)->exists());
    }

    /**
     * @param  array<string>  $defaultRoles
     */
    private function permission(string $key, array $defaultRoles): PermissionModel
    {
        [$module, $name] = explode(':', $key, 2);
        [$resource, $action] = explode('.', $name, 2);

        app(PermissionRegistryInterface::class)->register(new PermissionDefinitionDTO(
            key: $key,
            label: ucfirst($resource).' '.$action,
            resource: $resource,
            action: $action,
            module: $module,
            defaultRoles: $defaultRoles,
            description: ucfirst($resource).' '.$action.': description',
        ));

        return PermissionModel::query()->create([
            'key' => $key,
            'label' => $key,
            'resource' => $resource,
            'action' => $action,
            'module' => $module,
        ]);
    }

    private function enableTestModule(): void
    {
        $modules = new ModuleCollection;
        $modules->add(new Module(
            id: ModuleId::generate(),
            name: new ModuleName('test-module'),
            displayName: 'Test module',
            description: '',
            version: ModuleVersion::fromString('1.0.0'),
            author: 'Test',
            requirements: new ModuleRequirements(null, null, [], []),
            status: ModuleStatus::Enabled,
        ));

        $manager = $this->createStub(ModuleManagerServiceInterface::class);
        $manager->method('enabled')->willReturn($modules->enabled());
        $this->app->instance(ModuleManagerServiceInterface::class, $manager);
    }

    private function role(string $name): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
    }
}
