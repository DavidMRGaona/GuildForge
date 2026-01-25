<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Authorization\Entities;

use App\Domain\Authorization\Entities\Permission;
use App\Domain\Authorization\ValueObjects\PermissionId;
use App\Domain\Authorization\ValueObjects\PermissionKey;
use PHPUnit\Framework\TestCase;

final class PermissionTest extends TestCase
{
    public function test_it_creates_permission_with_required_data(): void
    {
        $id = PermissionId::generate();
        $key = new PermissionKey('events.create');
        $label = 'Create events';
        $resource = 'events';
        $action = 'create';

        $permission = new Permission(
            id: $id,
            key: $key,
            label: $label,
            resource: $resource,
            action: $action,
        );

        $this->assertEquals($id, $permission->id());
        $this->assertEquals($key, $permission->key());
        $this->assertEquals($label, $permission->label());
        $this->assertEquals($resource, $permission->resource());
        $this->assertEquals($action, $permission->action());
        $this->assertNull($permission->module());
    }

    public function test_it_creates_permission_with_all_data(): void
    {
        $id = PermissionId::generate();
        $key = new PermissionKey('announcements:announcements.view');
        $label = 'View announcements';
        $resource = 'announcements';
        $action = 'view';
        $module = 'announcements';

        $permission = new Permission(
            id: $id,
            key: $key,
            label: $label,
            resource: $resource,
            action: $action,
            module: $module,
        );

        $this->assertEquals($id, $permission->id());
        $this->assertEquals($key, $permission->key());
        $this->assertEquals($label, $permission->label());
        $this->assertEquals($resource, $permission->resource());
        $this->assertEquals($action, $permission->action());
        $this->assertEquals($module, $permission->module());
    }

    public function test_it_returns_correct_id(): void
    {
        $id = PermissionId::generate();

        $permission = $this->createPermission($id);

        $this->assertSame($id, $permission->id());
    }

    public function test_it_returns_correct_key(): void
    {
        $key = new PermissionKey('users.delete');

        $permission = $this->createPermission(key: $key);

        $this->assertSame($key, $permission->key());
    }

    public function test_it_returns_correct_label(): void
    {
        $label = 'Delete users';

        $permission = $this->createPermission(label: $label);

        $this->assertEquals($label, $permission->label());
    }

    public function test_it_returns_correct_resource(): void
    {
        $resource = 'articles';

        $permission = $this->createPermission(resource: $resource);

        $this->assertEquals($resource, $permission->resource());
    }

    public function test_it_returns_correct_action(): void
    {
        $action = 'update';

        $permission = $this->createPermission(action: $action);

        $this->assertEquals($action, $permission->action());
    }

    public function test_it_returns_null_module_for_core_permissions(): void
    {
        $permission = $this->createPermission();

        $this->assertNull($permission->module());
    }

    public function test_it_returns_correct_module_for_module_permissions(): void
    {
        $module = 'event-registrations';

        $permission = $this->createPermission(module: $module);

        $this->assertEquals($module, $permission->module());
    }

    public function test_belongs_to_module_returns_false_for_core_permissions(): void
    {
        $permission = $this->createPermission();

        $this->assertFalse($permission->belongsToModule());
    }

    public function test_belongs_to_module_returns_true_for_module_permissions(): void
    {
        $permission = $this->createPermission(module: 'announcements');

        $this->assertTrue($permission->belongsToModule());
    }

    public function test_equals_returns_true_for_same_id(): void
    {
        $id = PermissionId::generate();

        $permission1 = $this->createPermission($id);
        $permission2 = $this->createPermission($id);

        $this->assertTrue($permission1->equals($permission2));
    }

    public function test_equals_returns_false_for_different_ids(): void
    {
        $permission1 = $this->createPermission(PermissionId::generate());
        $permission2 = $this->createPermission(PermissionId::generate());

        $this->assertFalse($permission1->equals($permission2));
    }

    private function createPermission(
        ?PermissionId $id = null,
        ?PermissionKey $key = null,
        string $label = 'Test permission',
        string $resource = 'events',
        string $action = 'create',
        ?string $module = null,
    ): Permission {
        return new Permission(
            id: $id ?? PermissionId::generate(),
            key: $key ?? new PermissionKey('events.create'),
            label: $label,
            resource: $resource,
            action: $action,
            module: $module,
        );
    }
}
