<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Authorization;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Infrastructure\Authorization\CorePermissionDefinitions;
use Tests\TestCase;

final class CorePermissionDefinitionsTest extends TestCase
{
    public function test_new_core_permissions_exist_without_default_roles(): void
    {
        foreach (['modules.manage', 'modules.configure', 'dashboard.configure', 'pages.manage', 'users.assign_roles'] as $key) {
            $this->assertSame([], $this->findDefinition($key)->defaultRoles, $key);
        }
    }

    public function test_sensitive_core_permissions_have_a_description(): void
    {
        foreach (['settings.manage', 'users.assign_roles', 'modules.configure', 'modules.manage', 'pages.manage', 'admin.access'] as $key) {
            $this->assertNotEmpty($this->findDefinition($key)->description, $key);
        }
    }

    public function test_definition_dto_round_trips_description_and_group(): void
    {
        $dto = PermissionDefinitionDTO::fromArray([
            'key' => 'a.b',
            'label' => 'L',
            'resource' => 'a',
            'action' => 'b',
            'description' => 'D',
            'group' => 'G',
        ]);

        $this->assertSame('D', $dto->description);
        $this->assertSame('G', $dto->group);
        $this->assertSame('D', $dto->toArray()['description']);
        $this->assertSame('G', $dto->toArray()['group']);
    }

    public function test_it_defines_the_maintenance_permission_under_settings(): void
    {
        $permission = $this->findDefinition('settings.maintenance');

        $this->assertSame('settings', $permission->resource);
        $this->assertSame('maintenance', $permission->action);
        $this->assertSame([], $permission->defaultRoles);
        $this->assertSame(__('authorization.permissions.settings.maintenance'), $permission->label);
    }

    public function test_it_defines_the_apply_updates_permission_under_updates(): void
    {
        $permission = $this->findDefinition('updates.apply');

        $this->assertSame('updates', $permission->resource);
        $this->assertSame('apply', $permission->action);
        $this->assertSame([], $permission->defaultRoles);
        $this->assertSame(__('authorization.permissions.updates.apply'), $permission->label);
    }

    public function test_the_updates_resource_has_a_translated_group_label(): void
    {
        $this->assertNotSame('authorization.resources.updates', __('authorization.resources.updates'));
    }

    public function test_it_defines_the_menu_item_permissions_for_admins_only(): void
    {
        foreach (['view_any', 'view', 'create', 'update', 'delete'] as $action) {
            $permission = $this->findDefinition("menu_items.{$action}");

            $this->assertSame('menu_items', $permission->resource);
            $this->assertSame($action, $permission->action);
            $this->assertSame([], $permission->defaultRoles);
            $this->assertSame(__("authorization.permissions.menu_items.{$action}"), $permission->label);
            $this->assertNotSame("authorization.permissions.menu_items.{$action}", $permission->label);
        }
    }

    public function test_the_menu_items_resource_has_a_translated_group_label(): void
    {
        $this->assertSame('Menú de navegación', __('authorization.resources.menu_items', [], 'es'));
        $this->assertSame('Navigation menu', __('authorization.resources.menu_items', [], 'en'));
    }

    public function test_every_permission_label_is_translated_in_english(): void
    {
        app()->setLocale('en');

        foreach (CorePermissionDefinitions::all() as $definition) {
            $this->assertNotSame("authorization.permissions.{$definition->key}", $definition->label, $definition->key);
            $this->assertNotSame("authorization.resources.{$definition->resource}", __("authorization.resources.{$definition->resource}"), $definition->resource);
        }
    }

    private function findDefinition(string $key): PermissionDefinitionDTO
    {
        foreach (CorePermissionDefinitions::all() as $definition) {
            if ($definition->key === $key) {
                return $definition;
            }
        }

        $this->fail("Permission {$key} is not defined");
    }
}
