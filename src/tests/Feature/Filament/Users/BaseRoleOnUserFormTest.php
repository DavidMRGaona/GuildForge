<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
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
