<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Database\Seeders\RolesSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Admins and editors are whoever holds the role in the user_role pivot: the factory states and
 * make:admin-user must give it there, with the same result in isAdmin(), policies and the panel.
 */
final class UserRoleStatesTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    public function test_the_admin_state_gives_an_administrator(): void
    {
        $admin = UserModel::factory()->admin()->create();

        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->can('viewAny', UserModel::class));
        $this->assertTrue($admin->can('create', EventModel::class));
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_the_editor_state_gives_an_editor(): void
    {
        $editor = UserModel::factory()->editor()->create();

        $this->assertFalse($editor->isAdmin());
        $this->assertFalse($editor->can('viewAny', UserModel::class));
        $this->assertTrue($editor->can('create', EventModel::class));
        $this->assertTrue($editor->canAccessPanel(Filament::getPanel('admin')));
        $this->actingAs($editor)->get('/admin')->assertOk();
    }

    public function test_the_default_state_gives_a_user_without_panel_access(): void
    {
        $user = UserModel::factory()->create();

        $this->assertFalse($user->isAdmin());
        $this->assertFalse($user->can('viewAny', UserModel::class));
        $this->assertFalse($user->can('create', EventModel::class));
        $this->assertFalse($user->canAccessPanel(Filament::getPanel('admin')));
        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_make_admin_user_creates_an_administrator_with_the_member_role(): void
    {
        $this->artisan('make:admin-user')
            ->expectsQuestion('What is the username?', 'jefa')
            ->expectsQuestion('What is the email address?', 'jefa@example.com')
            ->expectsQuestion('What is the password?', 'secret-password')
            ->assertSuccessful();

        $admin = UserModel::query()->where('email', 'jefa@example.com')->sole();
        $roles = $admin->roles()->pluck('name')->sort()->values()->all();

        $this->assertSame(['admin', 'member'], $roles);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue($admin->can('viewAny', UserModel::class));
        $this->assertTrue($admin->canAccessPanel(Filament::getPanel('admin')));
    }
}
