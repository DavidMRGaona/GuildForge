<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\MenuItems;

use App\Filament\Resources\MenuItemResource;
use App\Infrastructure\Navigation\Persistence\Eloquent\Models\MenuItemModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * The navigation menu is managed by admins and by the roles granted the menu_items.* permissions.
 */
final class MenuItemAccessTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    public function test_editor_without_menu_permissions_cannot_open_the_menu_list(): void
    {
        $this->actingAs($this->editorWithPermissions(['events.view_any']));

        $this->get(MenuItemResource::getUrl('index'))->assertForbidden();
    }

    public function test_user_granted_menu_permissions_can_list_and_edit_menu_items(): void
    {
        $item = MenuItemModel::factory()->create();
        $this->actingAs($this->editorWithPermissions(['menu_items.view_any', 'menu_items.update']));

        $this->get(MenuItemResource::getUrl('index'))->assertOk();
        $this->get(MenuItemResource::getUrl('edit', ['record' => $item]))->assertOk();
    }

    public function test_a_core_seeded_item_cannot_be_deleted_even_with_delete_permission(): void
    {
        $user = $this->editorWithPermissions(['menu_items.view_any', 'menu_items.delete']);

        $this->assertFalse($user->can('delete', MenuItemModel::factory()->forModule(MenuItemModel::CORE_MODULE)->create()));
        $this->assertTrue($user->can('delete', MenuItemModel::factory()->forModule('announcements')->create()));
        $this->assertTrue($user->can('delete', MenuItemModel::factory()->create()));
    }

    public function test_admin_can_manage_menu_items(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $this->actingAs($admin);

        $this->get(MenuItemResource::getUrl('index'))->assertOk();
        $this->get(MenuItemResource::getUrl('create'))->assertOk();
    }
}
