<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Impersonation;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Impersonation\DTOs\ViewedRoleDTO;
use App\Application\Impersonation\Services\ImpersonationRolesInterface;
use App\Infrastructure\Impersonation\RoleViewAccount;
use App\Infrastructure\Persistence\Eloquent\Models\RoleAuditEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The throwaway account a role view runs as: written straight to the tables, so no model events,
 * no role holder cap and no role history, and never able to sign in.
 */
final class RoleViewAccountTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_it_creates_an_account_with_the_roles(): void
    {
        $roles = $this->rolesNamed('member', 'socio');

        $account = $this->account()->create($roles);

        $names = app(AuthorizationServiceInterface::class)->getRoles($account);
        $this->assertContains('member', $names);
        $this->assertContains('socio', $names);
        $this->assertSame(
            implode(' + ', array_map(static fn (ViewedRoleDTO $role): string => $role->displayName, $roles)),
            $account->display_name,
        );
        $this->assertStringEndsWith('@'.RoleViewAccount::EMAIL_DOMAIN, $account->email);
        $this->assertNotNull($account->email_verified_at);
    }

    public function test_each_account_gets_a_new_id(): void
    {
        $roles = $this->rolesNamed('member');

        $first = $this->account()->create($roles);
        $second = $this->account()->create($roles);

        $this->assertNotSame($first->id, $second->id);
    }

    public function test_its_password_never_matches_and_never_throws(): void
    {
        $account = $this->account()->create($this->rolesNamed('member'));

        $this->assertFalse(Hash::check('anything', $account->password));
    }

    public function test_it_fires_no_model_events(): void
    {
        $roles = $this->rolesNamed('member');
        Event::fake();

        $this->account()->create($roles);

        Event::assertNotDispatched('eloquent.created: '.UserModel::class);
    }

    public function test_it_writes_no_role_history(): void
    {
        $roles = $this->rolesNamed('member', 'socio');
        $before = RoleAuditEntryModel::query()->count();

        $this->account()->create($roles);

        $this->assertSame($before, RoleAuditEntryModel::query()->count());
    }

    public function test_a_role_that_requires_membership_is_inactive_without_socio(): void
    {
        $role = RoleModel::create([
            'name' => 'members-only',
            'display_name' => 'Members only',
            'requires_association_member' => true,
        ]);

        $account = $this->account()->create($this->rolesNamed('member', 'members-only'));

        $this->assertFalse(app(AuthorizationServiceInterface::class)->hasRole($account, $role->name));
    }

    private function account(): RoleViewAccount
    {
        return new RoleViewAccount;
    }

    /**
     * @return non-empty-list<ViewedRoleDTO>
     */
    private function rolesNamed(string ...$names): array
    {
        $ids = array_values(RoleModel::query()->whereIn('name', $names)->pluck('id')
            ->map(static fn (mixed $id): string => (string) $id)->all());
        $roles = app(ImpersonationRolesInterface::class)->find($ids);
        $this->assertCount(count($names), $roles);
        $this->assertNotEmpty($roles);

        return $roles;
    }
}
