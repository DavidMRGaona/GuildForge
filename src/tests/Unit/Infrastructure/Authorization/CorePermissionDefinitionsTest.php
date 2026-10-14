<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Authorization;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Infrastructure\Authorization\CorePermissionDefinitions;
use Tests\TestCase;

final class CorePermissionDefinitionsTest extends TestCase
{
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
