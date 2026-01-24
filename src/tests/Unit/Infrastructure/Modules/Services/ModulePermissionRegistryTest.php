<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Services;

use App\Application\Modules\DTOs\PermissionDTO;
use App\Infrastructure\Modules\Services\ModulePermissionRegistry;
use PHPUnit\Framework\TestCase;

final class ModulePermissionRegistryTest extends TestCase
{
    private ModulePermissionRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new ModulePermissionRegistry;
    }

    public function test_it_registers_single_permission(): void
    {
        $permission = new PermissionDTO(
            name: 'edit-posts',
            label: 'Edit Posts',
            group: 'content',
            module: 'blog',
        );

        $this->registry->register($permission);

        $this->assertTrue($this->registry->has('blog.edit-posts'));
    }

    public function test_it_registers_multiple_permissions(): void
    {
        $permissions = [
            new PermissionDTO(
                name: 'view-posts',
                label: 'View Posts',
                group: 'content',
                module: 'blog',
            ),
            new PermissionDTO(
                name: 'edit-posts',
                label: 'Edit Posts',
                group: 'content',
                module: 'blog',
            ),
        ];

        $this->registry->registerMany($permissions);

        $this->assertTrue($this->registry->has('blog.view-posts'));
        $this->assertTrue($this->registry->has('blog.edit-posts'));
    }

    public function test_all_returns_all_registered_permissions(): void
    {
        $permission1 = new PermissionDTO(
            name: 'view',
            label: 'View',
            group: 'general',
            module: 'blog',
        );
        $permission2 = new PermissionDTO(
            name: 'edit',
            label: 'Edit',
            group: 'general',
            module: 'forum',
        );

        $this->registry->register($permission1);
        $this->registry->register($permission2);

        $all = $this->registry->all();

        $this->assertCount(2, $all);
    }

    public function test_for_module_returns_permissions_for_specific_module(): void
    {
        $blogPermission = new PermissionDTO(
            name: 'edit-posts',
            label: 'Edit Posts',
            group: 'content',
            module: 'blog',
        );
        $forumPermission = new PermissionDTO(
            name: 'edit-threads',
            label: 'Edit Threads',
            group: 'content',
            module: 'forum',
        );

        $this->registry->register($blogPermission);
        $this->registry->register($forumPermission);

        $blogPermissions = $this->registry->forModule('blog');

        $this->assertCount(1, $blogPermissions);
        $this->assertEquals('edit-posts', $blogPermissions[0]->name);
    }

    public function test_for_module_returns_empty_array_when_no_permissions_for_module(): void
    {
        $permission = new PermissionDTO(
            name: 'view',
            label: 'View',
            group: 'general',
            module: 'blog',
        );

        $this->registry->register($permission);

        $result = $this->registry->forModule('nonexistent');

        $this->assertEmpty($result);
    }

    public function test_grouped_returns_permissions_grouped_by_group(): void
    {
        $permission1 = new PermissionDTO(
            name: 'view-posts',
            label: 'View Posts',
            group: 'content',
            module: 'blog',
        );
        $permission2 = new PermissionDTO(
            name: 'edit-posts',
            label: 'Edit Posts',
            group: 'content',
            module: 'blog',
        );
        $permission3 = new PermissionDTO(
            name: 'manage-users',
            label: 'Manage Users',
            group: 'users',
            module: 'auth',
        );

        $this->registry->register($permission1);
        $this->registry->register($permission2);
        $this->registry->register($permission3);

        $grouped = $this->registry->grouped();

        $this->assertArrayHasKey('content', $grouped);
        $this->assertArrayHasKey('users', $grouped);
        $this->assertCount(2, $grouped['content']);
        $this->assertCount(1, $grouped['users']);
    }

    public function test_grouped_returns_groups_sorted_alphabetically(): void
    {
        $permission1 = new PermissionDTO(
            name: 'perm1',
            label: 'Permission 1',
            group: 'zebra',
            module: 'test',
        );
        $permission2 = new PermissionDTO(
            name: 'perm2',
            label: 'Permission 2',
            group: 'alpha',
            module: 'test',
        );
        $permission3 = new PermissionDTO(
            name: 'perm3',
            label: 'Permission 3',
            group: 'beta',
            module: 'test',
        );

        $this->registry->register($permission1);
        $this->registry->register($permission2);
        $this->registry->register($permission3);

        $grouped = $this->registry->grouped();
        $keys = array_keys($grouped);

        $this->assertEquals(['alpha', 'beta', 'zebra'], $keys);
    }

    public function test_find_returns_permission_by_full_name(): void
    {
        $permission = new PermissionDTO(
            name: 'delete-posts',
            label: 'Delete Posts',
            group: 'content',
            module: 'blog',
        );

        $this->registry->register($permission);

        $found = $this->registry->find('blog.delete-posts');

        $this->assertNotNull($found);
        $this->assertEquals('delete-posts', $found->name);
        $this->assertEquals('blog', $found->module);
    }

    public function test_find_returns_null_when_permission_not_found(): void
    {
        $found = $this->registry->find('nonexistent.permission');

        $this->assertNull($found);
    }

    public function test_has_returns_true_when_permission_exists(): void
    {
        $permission = new PermissionDTO(
            name: 'view-dashboard',
            label: 'View Dashboard',
            group: 'general',
            module: 'admin',
        );

        $this->registry->register($permission);

        $this->assertTrue($this->registry->has('admin.view-dashboard'));
    }

    public function test_has_returns_false_when_permission_does_not_exist(): void
    {
        $this->assertFalse($this->registry->has('nonexistent.permission'));
    }

    public function test_unregister_module_removes_all_permissions_for_module(): void
    {
        $blogPermission1 = new PermissionDTO(
            name: 'view-posts',
            label: 'View Posts',
            group: 'content',
            module: 'blog',
        );
        $blogPermission2 = new PermissionDTO(
            name: 'edit-posts',
            label: 'Edit Posts',
            group: 'content',
            module: 'blog',
        );
        $forumPermission = new PermissionDTO(
            name: 'view-threads',
            label: 'View Threads',
            group: 'content',
            module: 'forum',
        );

        $this->registry->register($blogPermission1);
        $this->registry->register($blogPermission2);
        $this->registry->register($forumPermission);

        $this->registry->unregisterModule('blog');

        $this->assertFalse($this->registry->has('blog.view-posts'));
        $this->assertFalse($this->registry->has('blog.edit-posts'));
        $this->assertTrue($this->registry->has('forum.view-threads'));
    }

    public function test_clear_removes_all_permissions(): void
    {
        $permission1 = new PermissionDTO(
            name: 'perm1',
            label: 'Permission 1',
            group: 'group1',
            module: 'module1',
        );
        $permission2 = new PermissionDTO(
            name: 'perm2',
            label: 'Permission 2',
            group: 'group2',
            module: 'module2',
        );

        $this->registry->register($permission1);
        $this->registry->register($permission2);

        $this->registry->clear();

        $this->assertEmpty($this->registry->all());
        $this->assertFalse($this->registry->has('module1.perm1'));
        $this->assertFalse($this->registry->has('module2.perm2'));
    }

    public function test_it_handles_permissions_without_module(): void
    {
        $permission = new PermissionDTO(
            name: 'global-permission',
            label: 'Global Permission',
            group: 'global',
        );

        $this->registry->register($permission);

        $this->assertTrue($this->registry->has('global-permission'));
        $found = $this->registry->find('global-permission');
        $this->assertNotNull($found);
        $this->assertEquals('global-permission', $found->name);
    }

    public function test_registering_duplicate_permission_overwrites_previous(): void
    {
        $permission1 = new PermissionDTO(
            name: 'edit',
            label: 'Edit Original',
            group: 'content',
            module: 'blog',
        );
        $permission2 = new PermissionDTO(
            name: 'edit',
            label: 'Edit Updated',
            group: 'content',
            module: 'blog',
        );

        $this->registry->register($permission1);
        $this->registry->register($permission2);

        $found = $this->registry->find('blog.edit');
        $this->assertEquals('Edit Updated', $found->label);
    }

    public function test_for_module_returns_indexed_array(): void
    {
        $permission1 = new PermissionDTO(
            name: 'view',
            label: 'View',
            group: 'content',
            module: 'blog',
        );
        $permission2 = new PermissionDTO(
            name: 'edit',
            label: 'Edit',
            group: 'content',
            module: 'blog',
        );

        $this->registry->register($permission1);
        $this->registry->register($permission2);

        $permissions = $this->registry->forModule('blog');

        $this->assertArrayHasKey(0, $permissions);
        $this->assertArrayHasKey(1, $permissions);
    }
}
