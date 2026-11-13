<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Impersonation;

use App\Application\Authorization\DTOs\PermissionDefinitionDTO;
use App\Application\Authorization\Services\AdminOnlyPermissionRule;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\Services\ImpersonationEligibilityRule;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Domain\Impersonation\Enums\ImpersonationRefusal;
use App\Infrastructure\Authorization\CorePermissionDefinitions;
use App\Infrastructure\Impersonation\ImpersonationContext;
use App\Infrastructure\Persistence\Eloquent\Models\PermissionModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Who may impersonate whom: only the administrator role, and never another administrator,
 * oneself, a deactivated or an anonymized account. Checked when starting and on every request.
 */
final class ImpersonationEligibilityRuleTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_permission_is_defined_and_reserved_to_the_administrator_role(): void
    {
        $keys = array_map(
            static fn (PermissionDefinitionDTO $definition): string => $definition->key,
            CorePermissionDefinitions::all(),
        );

        $this->assertContains(ImpersonationEligibilityRule::PERMISSION, $keys);
        $this->assertContains(ImpersonationEligibilityRule::PERMISSION, AdminOnlyPermissionRule::CORE_KEYS);
        $this->assertFalse(app(AdminOnlyPermissionRule::class)->mayHold('editor', ImpersonationEligibilityRule::PERMISSION));
    }

    public function test_an_administrator_may_impersonate_a_member(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $member = UserModel::factory()->create();

        $this->assertTrue($this->rule()->mayImpersonate($admin));
        $this->assertNull($this->rule()->startRefusal($admin, $member));
        $this->assertTrue($this->rule()->mayStart($admin, $member));
        $this->assertNull($this->rule()->continuationRefusal($admin, $member));
    }

    public function test_an_account_with_panel_access_can_be_impersonated(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $editor = UserModel::factory()->editor()->create();

        $this->assertNull($this->rule()->startRefusal($admin, $editor));
    }

    public function test_an_editor_may_not_impersonate(): void
    {
        $editor = UserModel::factory()->editor()->create();
        $member = UserModel::factory()->create();

        $this->assertFalse($this->rule()->mayImpersonate($editor));
        $this->assertSame(ImpersonationRefusal::NotPermitted, $this->rule()->startRefusal($editor, $member));
        $this->assertSame(ImpersonationRefusal::NotPermitted, $this->rule()->continuationRefusal($editor, $member));
    }

    public function test_a_role_given_the_permission_by_hand_may_not_impersonate(): void
    {
        // Written around the role editor: AdminOnlyPermissionRule drops it from every other role
        $role = RoleModel::query()->create(['name' => 'presidency', 'display_name' => 'Presidency']);
        $permission = PermissionModel::query()->create([
            'key' => ImpersonationEligibilityRule::PERMISSION,
            'label' => ImpersonationEligibilityRule::PERMISSION,
            'resource' => 'users',
            'action' => 'impersonate',
        ]);
        $role->permissions()->attach($permission->id);
        $president = UserModel::factory()->create();
        $president->roles()->attach($role->id);
        $member = UserModel::factory()->create();

        $this->assertFalse($this->rule()->mayImpersonate($president));
        $this->assertSame(ImpersonationRefusal::NotPermitted, $this->rule()->startRefusal($president, $member));
    }

    public function test_an_administrator_cannot_be_impersonated(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $otherAdmin = UserModel::factory()->admin()->create();

        $this->assertSame(ImpersonationRefusal::TargetIsAdmin, $this->rule()->startRefusal($admin, $otherAdmin));
        $this->assertSame(ImpersonationRefusal::TargetIsAdmin, $this->rule()->continuationRefusal($admin, $otherAdmin));
    }

    public function test_nobody_can_impersonate_themselves(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertSame(ImpersonationRefusal::TargetIsSelf, $this->rule()->startRefusal($admin, $admin));
    }

    public function test_a_deactivated_account_cannot_be_impersonated(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $member = UserModel::factory()->create();
        $member->delete();

        $this->assertSame(ImpersonationRefusal::TargetDeactivated, $this->rule()->startRefusal($admin, $member));
        $this->assertSame(ImpersonationRefusal::TargetDeactivated, $this->rule()->continuationRefusal($admin, $member));
    }

    public function test_an_anonymized_account_cannot_be_impersonated(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $member = UserModel::factory()->create(['anonymized_at' => now()]);

        $this->assertSame(ImpersonationRefusal::TargetAnonymized, $this->rule()->startRefusal($admin, $member));
    }

    public function test_a_second_impersonation_cannot_start_while_one_is_under_way(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $member = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        app(ImpersonationContext::class)->activate($admin, $member, ImpersonationMode::ReadOnly, 'log-id');

        $this->assertSame(ImpersonationRefusal::AlreadyImpersonating, $this->rule()->startRefusal($admin, $other));
        // The one under way may go on
        $this->assertNull($this->rule()->continuationRefusal($admin, $member));
    }

    public function test_a_check_that_throws_refuses(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $member = UserModel::factory()->create();
        $authorization = $this->createStub(AuthorizationServiceInterface::class);
        $authorization->method('can')->willThrowException(new RuntimeException('cache down'));
        $authorization->method('hasRole')->willThrowException(new RuntimeException('cache down'));
        $this->app->instance(AuthorizationServiceInterface::class, $authorization);

        $this->assertFalse($this->rule()->mayImpersonate($admin));
        $this->assertSame(ImpersonationRefusal::CheckFailed, $this->rule()->startRefusal($admin, $member));
        $this->assertSame(ImpersonationRefusal::CheckFailed, $this->rule()->continuationRefusal($admin, $member));
    }

    private function rule(): ImpersonationEligibilityRule
    {
        return app(ImpersonationEligibilityRule::class);
    }
}
