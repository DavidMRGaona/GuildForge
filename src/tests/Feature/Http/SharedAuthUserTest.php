<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * What the frontend receives about the signed-in user. useAuth decides isAdmin/isEditor (and with
 * them the panel link in UserDropdown and can()/canAny()/canAll()) from auth.user.roles, and
 * can() falls back to auth.user.permissions for everyone else.
 */
final class SharedAuthUserTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const array USER_KEYS = [
        'id',
        'name',
        'displayName',
        'email',
        'avatarPublicId',
        'emailVerified',
        'createdAt',
        'roles',
        'permissions',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_a_guest_gets_no_user(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('auth.user', null));
    }

    public function test_an_admin_gets_the_admin_role_and_the_panel_link(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $user = $this->sharedUser($admin);

        $this->assertSame(['admin'], $user['roles']);
        $this->assertTrue($this->showsPanelLink($user));
    }

    public function test_an_editor_gets_the_editor_role_its_permissions_and_the_panel_link(): void
    {
        $editor = UserModel::factory()->editor()->create();

        $user = $this->sharedUser($editor);

        $this->assertSame(['editor'], $user['roles']);
        $this->assertContains('admin.access', $user['permissions']);
        $this->assertContains('events.create', $user['permissions']);
        $this->assertTrue($this->showsPanelLink($user));
    }

    public function test_a_member_gets_only_the_member_role_and_no_panel_link(): void
    {
        $member = UserModel::factory()->create();
        $member->roles()->attach($this->role('member')->id);

        $user = $this->sharedUser($member);

        $this->assertSame(['member'], $user['roles']);
        $this->assertNotContains('admin.access', $user['permissions']);
        $this->assertFalse($this->showsPanelLink($user));
    }

    public function test_an_association_member_gets_the_socio_role_and_no_panel_link(): void
    {
        $socio = UserModel::factory()->create();
        $socio->roles()->attach([
            $this->role('member')->id,
            $this->role(AssociationMembershipServiceInterface::DEFAULT_ROLE)->id,
        ]);

        $user = $this->sharedUser($socio);

        $roles = $user['roles'];
        sort($roles);
        $this->assertSame(['member', 'socio'], $roles);
        $this->assertNotContains('admin.access', $user['permissions']);
        $this->assertFalse($this->showsPanelLink($user));
    }

    public function test_a_user_without_roles_gets_no_roles_no_permissions_and_no_panel_link(): void
    {
        $user = $this->sharedUser(UserModel::factory()->create());

        $this->assertSame([], $user['roles']);
        $this->assertSame([], $user['permissions']);
        $this->assertFalse($this->showsPanelLink($user));
    }

    public function test_the_profile_page_gets_the_user_account_data(): void
    {
        $member = UserModel::factory()->create(['email' => 'perfil@example.com']);

        $this->actingAs($member)
            ->get(route('profile.show'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Profile/Show')
                ->where('user.id', $member->id)
                ->where('user.email', 'perfil@example.com')
                ->where('user.emailVerified', true)
                ->has('user.displayName')
                ->has('user.pendingEmail')
                ->has('user.avatarPublicId')
                ->has('user.createdAt'));
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedUser(UserModel $model): array
    {
        $shared = null;

        $this->actingAs($model)
            ->get('/')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$shared): void {
                $shared = $page->toArray()['props']['auth']['user'];
            });

        $this->assertIsArray($shared);
        foreach (self::USER_KEYS as $key) {
            $this->assertArrayHasKey($key, $shared);
        }
        $this->assertSame($model->id, $shared['id']);
        $this->assertIsArray($shared['roles']);
        $this->assertIsArray($shared['permissions']);

        /** @var array<string, mixed> $shared */
        return $shared;
    }

    /**
     * UserDropdown shows the panel link when useAuth says isAdmin or isEditor.
     *
     * @param  array<string, mixed>  $user
     */
    private function showsPanelLink(array $user): bool
    {
        /** @var list<string> $roles */
        $roles = $user['roles'];

        return in_array('admin', $roles, true) || in_array('editor', $roles, true);
    }

    private function role(string $name): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
    }
}
