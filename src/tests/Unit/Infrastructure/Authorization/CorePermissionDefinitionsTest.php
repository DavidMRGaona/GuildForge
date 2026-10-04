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
