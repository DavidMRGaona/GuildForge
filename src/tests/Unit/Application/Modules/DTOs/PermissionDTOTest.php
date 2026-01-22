<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Modules\DTOs;

use App\Application\Modules\DTOs\PermissionDTO;
use PHPUnit\Framework\TestCase;

final class PermissionDTOTest extends TestCase
{
    public function test_it_creates_permission_dto_from_constructor_with_all_properties(): void
    {
        $dto = new PermissionDTO(
            name: 'edit-posts',
            label: 'Edit Posts',
            group: 'content',
            description: 'Allows editing blog posts',
            module: 'blog',
            roles: ['editor', 'admin'],
        );

        $this->assertEquals('edit-posts', $dto->name);
        $this->assertEquals('Edit Posts', $dto->label);
        $this->assertEquals('content', $dto->group);
        $this->assertEquals('Allows editing blog posts', $dto->description);
        $this->assertEquals('blog', $dto->module);
        $this->assertEquals(['editor', 'admin'], $dto->roles);
    }

    public function test_it_creates_from_array_with_all_fields(): void
    {
        $data = [
            'name' => 'delete-users',
            'label' => 'Delete Users',
            'group' => 'user-management',
            'description' => 'Allows deleting user accounts',
            'module' => 'users',
            'roles' => ['admin'],
        ];

        $dto = PermissionDTO::fromArray($data);

        $this->assertEquals('delete-users', $dto->name);
        $this->assertEquals('Delete Users', $dto->label);
        $this->assertEquals('user-management', $dto->group);
        $this->assertEquals('Allows deleting user accounts', $dto->description);
        $this->assertEquals('users', $dto->module);
        $this->assertEquals(['admin'], $dto->roles);
    }

    public function test_it_creates_from_array_with_minimal_fields(): void
    {
        $data = [
            'name' => 'view-dashboard',
            'label' => 'View Dashboard',
            'group' => 'general',
        ];

        $dto = PermissionDTO::fromArray($data);

        $this->assertEquals('view-dashboard', $dto->name);
        $this->assertEquals('View Dashboard', $dto->label);
        $this->assertEquals('general', $dto->group);
        $this->assertNull($dto->description);
        $this->assertNull($dto->module);
        $this->assertEmpty($dto->roles);
    }

    public function test_it_handles_empty_strings_in_from_array(): void
    {
        $data = [];

        $dto = PermissionDTO::fromArray($data);

        $this->assertEquals('', $dto->name);
        $this->assertEquals('', $dto->label);
        $this->assertEquals('default', $dto->group);
        $this->assertNull($dto->description);
        $this->assertNull($dto->module);
        $this->assertEmpty($dto->roles);
    }

    public function test_to_array_returns_correct_representation(): void
    {
        $dto = new PermissionDTO(
            name: 'manage-events',
            label: 'Manage Events',
            group: 'events',
            description: 'Full event management access',
            module: 'events',
            roles: ['organizer', 'admin'],
        );

        $array = $dto->toArray();

        $expected = [
            'name' => 'manage-events',
            'label' => 'Manage Events',
            'group' => 'events',
            'description' => 'Full event management access',
            'module' => 'events',
            'roles' => ['organizer', 'admin'],
        ];

        $this->assertEquals($expected, $array);
    }

    public function test_to_array_includes_null_values(): void
    {
        $dto = new PermissionDTO(
            name: 'basic-permission',
            label: 'Basic Permission',
            group: 'basic',
        );

        $array = $dto->toArray();

        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('module', $array);
        $this->assertNull($array['description']);
        $this->assertNull($array['module']);
    }

    public function test_full_name_returns_name_with_module_prefix(): void
    {
        $dto = new PermissionDTO(
            name: 'edit-posts',
            label: 'Edit Posts',
            group: 'content',
            module: 'blog',
        );

        $this->assertEquals('blog.edit-posts', $dto->fullName());
    }

    public function test_full_name_returns_plain_name_when_no_module(): void
    {
        $dto = new PermissionDTO(
            name: 'edit-posts',
            label: 'Edit Posts',
            group: 'content',
        );

        $this->assertEquals('edit-posts', $dto->fullName());
    }

    public function test_belongs_to_module_returns_true_for_matching_module(): void
    {
        $dto = new PermissionDTO(
            name: 'view-posts',
            label: 'View Posts',
            group: 'content',
            module: 'blog',
        );

        $this->assertTrue($dto->belongsToModule('blog'));
    }

    public function test_belongs_to_module_returns_false_for_non_matching_module(): void
    {
        $dto = new PermissionDTO(
            name: 'view-posts',
            label: 'View Posts',
            group: 'content',
            module: 'blog',
        );

        $this->assertFalse($dto->belongsToModule('forum'));
    }

    public function test_belongs_to_module_returns_false_when_no_module_set(): void
    {
        $dto = new PermissionDTO(
            name: 'view-posts',
            label: 'View Posts',
            group: 'content',
        );

        $this->assertFalse($dto->belongsToModule('blog'));
    }

    public function test_it_handles_multiple_roles(): void
    {
        $dto = new PermissionDTO(
            name: 'moderate-content',
            label: 'Moderate Content',
            group: 'moderation',
            roles: ['moderator', 'admin', 'super-admin'],
        );

        $this->assertCount(3, $dto->roles);
        $this->assertContains('moderator', $dto->roles);
        $this->assertContains('admin', $dto->roles);
        $this->assertContains('super-admin', $dto->roles);
    }

    public function test_it_handles_empty_roles_array(): void
    {
        $dto = new PermissionDTO(
            name: 'view-public',
            label: 'View Public',
            group: 'public',
            roles: [],
        );

        $this->assertEmpty($dto->roles);
        $this->assertIsArray($dto->roles);
    }

    public function test_full_name_with_complex_module_name(): void
    {
        $dto = new PermissionDTO(
            name: 'export-data',
            label: 'Export Data',
            group: 'reports',
            module: 'advanced-reporting',
        );

        $this->assertEquals('advanced-reporting.export-data', $dto->fullName());
    }

    public function test_belongs_to_module_is_case_sensitive(): void
    {
        $dto = new PermissionDTO(
            name: 'edit',
            label: 'Edit',
            group: 'content',
            module: 'Blog',
        );

        $this->assertTrue($dto->belongsToModule('Blog'));
        $this->assertFalse($dto->belongsToModule('blog'));
    }
}
