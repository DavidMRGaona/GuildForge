<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Filament\Resources\UserResource\Pages\CreateUser;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Seeders\RolesSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * "member" is the base role of every account: modules grant it permissions (cancelling one's
 * own bookings, for instance), so every way of creating a user must give it.
 */
final class MemberRoleOnAccountCreationTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_a_registered_user_gets_the_member_role(): void
    {
        $this->memberRole();

        $this->register('nuevo@example.com')->assertRedirect(route('home'));

        $user = UserModel::query()->where('email', 'nuevo@example.com')->sole();
        $this->assertTrue($this->authorization()->hasRole($user, 'member'));
    }

    public function test_registration_works_without_the_member_role(): void
    {
        // A migration creates it: remove it to simulate a database where it was deleted
        RoleModel::query()->where('name', 'member')->delete();
        Log::spy();

        $this->register('nuevo@example.com')->assertRedirect(route('home'));

        $user = UserModel::query()->where('email', 'nuevo@example.com')->sole();
        $this->assertSame([], $this->authorization()->getRoles($user));
        $this->assertAuthenticatedAs($user);
        Log::shouldHaveReceived('warning')->withArgs(
            static fn (string $message): bool => str_contains($message, 'member')
        )->once();
    }

    public function test_a_user_created_in_the_panel_keeps_the_chosen_roles_and_gets_member(): void
    {
        $member = $this->memberRole();
        $editor = RoleModel::query()->create(['name' => 'editor', 'display_name' => 'Editor']);
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'nuevo',
                'email' => 'nuevo@example.com',
                'password' => 'secret-password',
                'roles' => [$editor->id],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = UserModel::query()->where('email', 'nuevo@example.com')->sole();
        $this->assertEqualsCanonicalizing([$editor->id, $member->id], $user->roles()->pluck('roles.id')->all());
        $this->assertTrue($this->authorization()->hasRole($user, 'editor'));
        $this->assertTrue($this->authorization()->hasRole($user, 'member'));
    }

    public function test_a_user_created_in_the_panel_without_roles_gets_member(): void
    {
        $this->memberRole();
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(CreateUser::class)
            ->fillForm([
                'name' => 'nuevo',
                'email' => 'nuevo@example.com',
                'password' => 'secret-password',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = UserModel::query()->where('email', 'nuevo@example.com')->sole();
        $this->assertSame(['member'], $this->authorization()->getRoles($user));
    }

    public function test_the_panel_explains_that_member_is_always_added(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(CreateUser::class)
            ->assertSee(__('filament.users.fields.rolesHelp'));
    }

    public function test_the_seeded_admin_and_editor_get_member(): void
    {
        $this->seed([RolesSeeder::class, UserSeeder::class]);

        foreach (['admin@example.local' => 'admin', 'editor@example.local' => 'editor'] as $email => $role) {
            $user = UserModel::query()->where('email', $email)->sole();
            $this->assertTrue($this->authorization()->hasRole($user, $role));
            $this->assertTrue($this->authorization()->hasRole($user, 'member'), "{$email} has no member role");
        }
    }

    public function test_the_seeded_users_get_member_through_the_member_role_service(): void
    {
        $this->seed(RolesSeeder::class);
        $memberRole = $this->spy(MemberRoleServiceInterface::class);

        $this->seed(UserSeeder::class);

        $seeded = UserModel::query()->count();
        $this->assertGreaterThan(0, $seeded);
        $memberRole->shouldHaveReceived('assignTo')->times($seeded);
    }

    public function test_an_admin_created_from_the_console_gets_member(): void
    {
        $this->memberRole();
        RoleModel::query()->create(['name' => 'admin', 'display_name' => 'Administrator', 'is_protected' => true]);

        $this->artisan('make:admin-user')
            ->expectsQuestion('What is the username?', 'jefa')
            ->expectsQuestion('What is the email address?', 'jefa@example.com')
            ->expectsQuestion('What is the password?', 'secret-password')
            ->assertSuccessful();

        $user = UserModel::query()->where('email', 'jefa@example.com')->sole();
        $this->assertTrue($this->authorization()->hasRole($user, 'admin'));
        $this->assertTrue($this->authorization()->hasRole($user, 'member'));
    }

    private function register(string $email): TestResponse
    {
        return $this->post('/registro', [
            'name' => 'Nuevo usuario',
            'email' => $email,
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ]);
    }

    private function memberRole(): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
    }

    private function authorization(): AuthorizationServiceInterface
    {
        return app(AuthorizationServiceInterface::class);
    }
}
