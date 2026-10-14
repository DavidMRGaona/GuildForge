<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Forms\Components\CheckboxList;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * "member" is the base role of every account and modules grant it what any signed-in user
 * needs: the roles checkbox list of the user form must never take it away, while every other
 * role keeps being granted and revoked there as usual.
 */
final class BaseRoleOnUserFormTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(UserModel::factory()->admin()->create());
    }

    // Behaviour that must not change

    /**
     * @return array<string, array{string}>
     */
    public static function otherRoles(): array
    {
        return [
            'admin' => ['admin'],
            'editor' => ['editor'],
            'socio' => ['socio'],
            'custom role' => ['game_master'],
        ];
    }

    #[DataProvider('otherRoles')]
    public function test_another_role_is_granted_from_the_user_form(string $roleName): void
    {
        $role = $this->role($roleName);
        $user = $this->userWithMember();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['roles' => [$this->member()->id, $role->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing([$roleName, 'member'], $this->authorization()->getRoles($user));
    }

    #[DataProvider('otherRoles')]
    public function test_another_role_is_revoked_from_the_user_form(string $roleName): void
    {
        $role = $this->role($roleName);
        $user = $this->userWithMember();
        $user->roles()->attach($role->id);
        $this->authorization()->invalidateCache();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['roles' => [$this->member()->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['member'], $this->authorization()->getRoles($user));
    }

    public function test_saving_an_anonymized_account_leaves_it_without_roles(): void
    {
        // Anonymizing removes every role on purpose: saving its form must not give one back
        $user = $this->userWithMember();
        $user->delete();
        app(UserServiceInterface::class)->anonymize($user->id);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame([], $user->fresh()?->roles()->pluck('name')->all());
    }

    // The base role cannot be taken away

    public function test_the_edit_form_shows_the_base_role_ticked_and_disabled(): void
    {
        $member = $this->member();
        $editor = $this->role('editor');
        $user = $this->userWithMember();

        $page = Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->assertSee(__('filament.users.fields.rolesHelp'));

        $this->assertContains($member->id, $page->get('data.roles'));
        $this->assertTrue($this->rolesField($page)->isOptionDisabled($member->id, $member->display_name));
        $this->assertFalse($this->rolesField($page)->isOptionDisabled($editor->id, $editor->display_name));
    }

    public function test_the_create_form_shows_the_base_role_ticked_and_disabled(): void
    {
        $member = $this->member();

        $page = Livewire::test(CreateUser::class)
            ->assertSee(__('filament.users.fields.rolesHelp'));

        $this->assertContains($member->id, $page->get('data.roles'));
        $this->assertTrue($this->rolesField($page)->isOptionDisabled($member->id, $member->display_name));
    }

    public function test_an_account_without_the_base_role_sees_it_ticked_and_gets_it_on_save(): void
    {
        $user = UserModel::factory()->create();

        $page = Livewire::test(EditUser::class, ['record' => $user->getRouteKey()]);

        $this->assertContains($this->member()->id, $page->get('data.roles'));
        $page->call('save')->assertHasNoFormErrors();
        $this->assertSame(['member'], $this->authorization()->getRoles($user));
    }

    public function test_saving_with_the_disabled_base_role_ticked_keeps_every_role(): void
    {
        $user = $this->userWithMember();
        $user->roles()->attach($this->role('editor')->id);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['editor', 'member'], $this->authorization()->getRoles($user));
    }

    public function test_unticking_the_base_role_keeps_it(): void
    {
        $editor = $this->role('editor');
        $user = $this->userWithMember();
        $user->roles()->attach($editor->id);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['roles' => [$editor->id]])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['editor', 'member'], $this->authorization()->getRoles($user));
    }

    public function test_deselecting_all_keeps_only_the_base_role(): void
    {
        $user = $this->userWithMember();
        $user->roles()->attach([$this->role('editor')->id, $this->role('socio')->id]);

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->fillForm(['roles' => []])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame(['member'], $this->authorization()->getRoles($user));
        $this->assertSame(['member'], $user->roles()->pluck('name')->all());
    }

    public function test_a_forged_roles_state_without_the_base_role_keeps_it(): void
    {
        $editor = $this->role('editor');
        $user = $this->userWithMember();

        Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
            ->set('data.roles', [$editor->id])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertEqualsCanonicalizing(['editor', 'member'], $user->roles()->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['editor', 'member'], $this->authorization()->getRoles($user));
    }

    private function rolesField(Testable $page): CheckboxList
    {
        $field = $page->instance()->form->getFlatFields(withHidden: true)['roles'] ?? null;
        $this->assertInstanceOf(CheckboxList::class, $field);

        return $field;
    }

    private function userWithMember(): UserModel
    {
        $user = UserModel::factory()->create();
        $user->roles()->attach($this->member()->id);

        return $user;
    }

    private function member(): RoleModel
    {
        return RoleModel::query()->where('name', 'member')->sole();
    }

    private function role(string $name): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => ucfirst($name)]);
    }

    private function authorization(): AuthorizationServiceInterface
    {
        return app(AuthorizationServiceInterface::class);
    }
}
