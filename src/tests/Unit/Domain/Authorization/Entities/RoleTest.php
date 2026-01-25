<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Authorization\Entities;

use App\Domain\Authorization\Entities\Role;
use App\Domain\Authorization\ValueObjects\RoleId;
use App\Domain\Authorization\ValueObjects\RoleName;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    public function test_it_creates_role_with_required_data(): void
    {
        $id = RoleId::generate();
        $name = new RoleName('admin');
        $displayName = 'Administrator';
        $description = 'Full system access';

        $role = new Role(
            id: $id,
            name: $name,
            displayName: $displayName,
            description: $description,
        );

        $this->assertEquals($id, $role->id());
        $this->assertEquals($name, $role->name());
        $this->assertEquals($displayName, $role->displayName());
        $this->assertEquals($description, $role->description());
        $this->assertFalse($role->isProtected());
    }

    public function test_it_creates_role_with_all_data(): void
    {
        $id = RoleId::generate();
        $name = new RoleName('super-admin');
        $displayName = 'Super Administrator';
        $description = 'System administrator with full privileges';
        $isProtected = true;

        $role = new Role(
            id: $id,
            name: $name,
            displayName: $displayName,
            description: $description,
            isProtected: $isProtected,
        );

        $this->assertEquals($id, $role->id());
        $this->assertEquals($name, $role->name());
        $this->assertEquals($displayName, $role->displayName());
        $this->assertEquals($description, $role->description());
        $this->assertTrue($role->isProtected());
    }

    public function test_it_returns_correct_id(): void
    {
        $id = RoleId::generate();

        $role = $this->createRole($id);

        $this->assertSame($id, $role->id());
    }

    public function test_it_returns_correct_name(): void
    {
        $name = new RoleName('content-editor');

        $role = $this->createRole(name: $name);

        $this->assertSame($name, $role->name());
    }

    public function test_it_returns_correct_display_name(): void
    {
        $displayName = 'Content Editor';

        $role = $this->createRole(displayName: $displayName);

        $this->assertEquals($displayName, $role->displayName());
    }

    public function test_it_returns_correct_description(): void
    {
        $description = 'Manages content and articles';

        $role = $this->createRole(description: $description);

        $this->assertEquals($description, $role->description());
    }

    public function test_is_protected_returns_true_for_protected_roles(): void
    {
        $role = $this->createRole(isProtected: true);

        $this->assertTrue($role->isProtected());
    }

    public function test_is_protected_returns_false_by_default(): void
    {
        $role = $this->createRole();

        $this->assertFalse($role->isProtected());
    }

    public function test_is_protected_returns_false_for_non_protected_roles(): void
    {
        $role = $this->createRole(isProtected: false);

        $this->assertFalse($role->isProtected());
    }

    public function test_equals_returns_true_for_same_id(): void
    {
        $id = RoleId::generate();

        $role1 = $this->createRole($id);
        $role2 = $this->createRole($id);

        $this->assertTrue($role1->equals($role2));
    }

    public function test_equals_returns_false_for_different_ids(): void
    {
        $role1 = $this->createRole(RoleId::generate());
        $role2 = $this->createRole(RoleId::generate());

        $this->assertFalse($role1->equals($role2));
    }

    private function createRole(
        ?RoleId $id = null,
        ?RoleName $name = null,
        string $displayName = 'Test Role',
        string $description = 'Test role description',
        bool $isProtected = false,
    ): Role {
        return new Role(
            id: $id ?? RoleId::generate(),
            name: $name ?? new RoleName('test-role'),
            displayName: $displayName,
            description: $description,
            isProtected: $isProtected,
        );
    }
}
