<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Users;

use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\RestoreAction;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class ManageAccountsBeyondYourRolesTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private const array USER_PERMISSIONS = ['users.view_any', 'users.view', 'users.update', 'users.delete'];

    public function test_cannot_open_edit_page_of_an_admin(): void
    {
        $this->actingAs($this->editorWithPermissions(self::USER_PERMISSIONS));
        $admin = UserModel::factory()->admin()->create();

        $this->get(EditUser::getUrl(['record' => $admin]))->assertForbidden();
    }

    public function test_cannot_deactivate_or_anonymize_an_admin_from_the_table(): void
    {
        $this->actingAs($this->editorWithPermissions(self::USER_PERMISSIONS));
        $admin = UserModel::factory()->admin()->create();
        $trashedAdmin = UserModel::factory()->admin()->create();
        $trashedAdmin->delete();

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('deactivate', $admin)
            ->assertTableActionHidden('sendPasswordResetEmail', $admin)
            ->assertTableActionHidden('sendVerificationEmail', $admin)
            ->assertTableActionHidden('anonymize', $trashedAdmin)
            ->assertTableActionHidden(RestoreAction::class, $trashedAdmin);
    }

    public function test_can_manage_an_account_whose_roles_are_all_grantable(): void
    {
        $this->actingAs($this->editorWithPermissions(self::USER_PERMISSIONS));
        $plain = UserModel::factory()->create();

        $this->get(EditUser::getUrl(['record' => $plain]))->assertOk();
        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('deactivate', $plain)
            ->assertTableActionVisible('sendPasswordResetEmail', $plain);
    }

    public function test_cannot_manage_an_account_holding_permissions_you_lack(): void
    {
        $this->actingAs($this->editorWithPermissions(self::USER_PERMISSIONS));
        $other = $this->editorWithPermissions(['events.unheld_permission']);

        $this->get(EditUser::getUrl(['record' => $other]))->assertForbidden();
        Livewire::test(ListUsers::class)->assertTableActionHidden('deactivate', $other);
    }

    public function test_deactivate_action_needs_users_delete(): void
    {
        $this->actingAs($this->editorWithPermissions(['users.view_any', 'users.view', 'users.update']));
        $plain = UserModel::factory()->create();

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('deactivate', $plain)
            ->assertTableActionVisible('sendPasswordResetEmail', $plain);
    }
}
