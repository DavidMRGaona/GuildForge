<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Authorization;

use App\Application\Authorization\DTOs\UpdateRoleDTO;
use App\Application\Authorization\Services\RoleRestrictionRule;
use App\Application\Authorization\Services\RoleServiceInterface;
use App\Domain\Authorization\Exceptions\RoleProtectedException;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class RoleRestrictionRuleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_member_and_association_role_cannot_be_restricted(): void
    {
        $rule = app(RoleRestrictionRule::class);

        $this->assertFalse($rule->canBeRestricted('admin'));
        $this->assertFalse($rule->canBeRestricted('member'));
        $this->assertFalse($rule->canBeRestricted('socio'));
    }

    public function test_any_other_role_can_be_restricted(): void
    {
        $this->assertTrue(app(RoleRestrictionRule::class)->canBeRestricted('junta'));
    }

    public function test_role_service_refuses_a_requirement_on_the_association_role(): void
    {
        $role = RoleModel::query()->where('name', 'socio')->firstOrFail();
        $role->update(['requires_association_member' => true]);

        $this->expectException(RoleProtectedException::class);

        app(RoleServiceInterface::class)->update($role->id, new UpdateRoleDTO(displayName: 'Socios'));
    }

    public function test_role_service_update_keeps_the_restrictions_of_a_regular_role(): void
    {
        $role = RoleModel::query()->create([
            'name' => 'junta',
            'display_name' => 'Junta',
            'requires_association_member' => true,
            'max_holders' => 3,
        ]);

        app(RoleServiceInterface::class)->update($role->id, new UpdateRoleDTO(displayName: 'Junta directiva'));

        $role->refresh();
        $this->assertTrue($role->requires_association_member);
        $this->assertSame(3, $role->max_holders);
    }
}
