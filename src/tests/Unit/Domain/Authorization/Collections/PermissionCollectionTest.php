<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Authorization\Collections;

use App\Domain\Authorization\Collections\PermissionCollection;
use App\Domain\Authorization\Entities\Permission;
use App\Domain\Authorization\ValueObjects\PermissionId;
use App\Domain\Authorization\ValueObjects\PermissionKey;
use PHPUnit\Framework\TestCase;

final class PermissionCollectionTest extends TestCase
{
    public function test_it_creates_empty_collection(): void
    {
        $collection = new PermissionCollection();

        $this->assertTrue($collection->isEmpty());
        $this->assertEquals(0, $collection->count());
    }

    public function test_it_creates_collection_with_permissions(): void
    {
        $permission1 = $this->createPermission('events.create');
        $permission2 = $this->createPermission('events.delete');

        $collection = new PermissionCollection($permission1, $permission2);

        $this->assertFalse($collection->isEmpty());
        $this->assertEquals(2, $collection->count());
    }

    public function test_it_adds_single_permission(): void
    {
        $collection = new PermissionCollection();
        $permission = $this->createPermission('events.create');

        $collection->add($permission);

        $this->assertEquals(1, $collection->count());
        $this->assertTrue($collection->has(new PermissionKey('events.create')));
    }

    public function test_it_adds_multiple_permissions(): void
    {
        $collection = new PermissionCollection();
        $permission1 = $this->createPermission('events.create');
        $permission2 = $this->createPermission('events.delete');
        $permission3 = $this->createPermission('users.view');

        $collection->addMany([$permission1, $permission2, $permission3]);

        $this->assertEquals(3, $collection->count());
    }

    public function test_all_returns_all_permissions(): void
    {
        $permission1 = $this->createPermission('events.create');
        $permission2 = $this->createPermission('events.delete');
        $collection = new PermissionCollection($permission1, $permission2);

        $result = $collection->all();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContainsOnlyInstancesOf(Permission::class, $result);
    }

    public function test_find_by_key_returns_permission(): void
    {
        $permission = $this->createPermission('events.create');
        $collection = new PermissionCollection($permission);

        $result = $collection->findByKey(new PermissionKey('events.create'));

        $this->assertNotNull($result);
        $this->assertEquals('events.create', (string) $result->key());
    }

    public function test_find_by_key_returns_null_if_not_found(): void
    {
        $collection = new PermissionCollection();

        $result = $collection->findByKey(new PermissionKey('nonexistent.permission'));

        $this->assertNull($result);
    }

    public function test_has_returns_true_if_permission_exists(): void
    {
        $permission = $this->createPermission('events.create');
        $collection = new PermissionCollection($permission);

        $this->assertTrue($collection->has(new PermissionKey('events.create')));
    }

    public function test_has_returns_false_if_permission_does_not_exist(): void
    {
        $collection = new PermissionCollection();

        $this->assertFalse($collection->has(new PermissionKey('nonexistent.permission')));
    }

    public function test_for_resource_filters_by_resource(): void
    {
        $events1 = $this->createPermission('events.create', 'events');
        $events2 = $this->createPermission('events.delete', 'events');
        $users = $this->createPermission('users.view', 'users');
        $collection = new PermissionCollection($events1, $events2, $users);

        $result = $collection->forResource('events');

        $this->assertEquals(2, $result->count());
        $this->assertTrue($result->has(new PermissionKey('events.create')));
        $this->assertTrue($result->has(new PermissionKey('events.delete')));
        $this->assertFalse($result->has(new PermissionKey('users.view')));
    }

    public function test_for_resource_returns_empty_collection_if_no_match(): void
    {
        $collection = new PermissionCollection(
            $this->createPermission('events.create', 'events')
        );

        $result = $collection->forResource('users');

        $this->assertTrue($result->isEmpty());
    }

    public function test_for_module_filters_by_module(): void
    {
        $core = $this->createPermission('events.create', 'events', null);
        $announcements1 = $this->createPermission('announcements:announcements.view', 'announcements', 'announcements');
        $announcements2 = $this->createPermission('announcements:announcements.create', 'announcements', 'announcements');
        $registrations = $this->createPermission('event-registrations:registrations.view', 'registrations', 'event-registrations');
        $collection = new PermissionCollection($core, $announcements1, $announcements2, $registrations);

        $result = $collection->forModule('announcements');

        $this->assertEquals(2, $result->count());
        $this->assertTrue($result->has(new PermissionKey('announcements:announcements.view')));
        $this->assertTrue($result->has(new PermissionKey('announcements:announcements.create')));
        $this->assertFalse($result->has(new PermissionKey('events.create')));
    }

    public function test_for_module_returns_empty_collection_if_no_match(): void
    {
        $collection = new PermissionCollection(
            $this->createPermission('events.create', 'events', null)
        );

        $result = $collection->forModule('announcements');

        $this->assertTrue($result->isEmpty());
    }

    public function test_grouped_returns_permissions_grouped_by_resource(): void
    {
        $events1 = $this->createPermission('events.create', 'events');
        $events2 = $this->createPermission('events.delete', 'events');
        $users = $this->createPermission('users.view', 'users');
        $collection = new PermissionCollection($events1, $events2, $users);

        $result = $collection->grouped();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('events', $result);
        $this->assertArrayHasKey('users', $result);
        $this->assertCount(2, $result['events']);
        $this->assertCount(1, $result['users']);
    }

    public function test_grouped_returns_empty_array_for_empty_collection(): void
    {
        $collection = new PermissionCollection();

        $result = $collection->grouped();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function test_remove_removes_permission(): void
    {
        $permission = $this->createPermission('events.create');
        $collection = new PermissionCollection($permission);

        $collection->remove(new PermissionKey('events.create'));

        $this->assertFalse($collection->has(new PermissionKey('events.create')));
        $this->assertTrue($collection->isEmpty());
    }

    public function test_remove_does_nothing_if_permission_not_found(): void
    {
        $permission = $this->createPermission('events.create');
        $collection = new PermissionCollection($permission);

        $collection->remove(new PermissionKey('nonexistent.permission'));

        $this->assertEquals(1, $collection->count());
    }

    public function test_count_returns_correct_count(): void
    {
        $collection = new PermissionCollection(
            $this->createPermission('events.create'),
            $this->createPermission('events.delete'),
            $this->createPermission('users.view')
        );

        $this->assertEquals(3, $collection->count());
    }

    public function test_is_empty_returns_true_for_empty_collection(): void
    {
        $collection = new PermissionCollection();

        $this->assertTrue($collection->isEmpty());
    }

    public function test_is_empty_returns_false_for_non_empty_collection(): void
    {
        $collection = new PermissionCollection(
            $this->createPermission('events.create')
        );

        $this->assertFalse($collection->isEmpty());
    }

    private function createPermission(
        string $keyValue,
        string $resource = 'events',
        ?string $module = null,
    ): Permission {
        $key = new PermissionKey($keyValue);

        return new Permission(
            id: PermissionId::generate(),
            key: $key,
            label: "Test {$keyValue}",
            resource: $resource,
            action: explode('.', explode(':', $keyValue)[1] ?? $keyValue)[1] ?? 'create',
            module: $module,
        );
    }
}
