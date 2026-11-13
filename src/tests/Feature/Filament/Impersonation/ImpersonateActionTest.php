<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Impersonation;

use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\Pages\ListUsers;
use App\Infrastructure\Persistence\Eloquent\Models\ImpersonationLogEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * «Impersonar» in the user list menu and in the header of the user page (core#578).
 */
final class ImpersonateActionTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private UserModel $admin;

    private UserModel $member;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = UserModel::factory()->admin()->create();
        $this->member = UserModel::factory()->create();
        $this->actingAs($this->admin);
    }

    public function test_the_row_action_is_offered_for_an_account_that_may_be_impersonated(): void
    {
        Livewire::test(ListUsers::class)
            ->assertTableActionVisible('impersonate', $this->member);
    }

    public function test_the_row_action_is_not_offered_for_administrators_or_oneself(): void
    {
        $otherAdmin = UserModel::factory()->admin()->create();

        Livewire::test(ListUsers::class)
            ->assertTableActionHidden('impersonate', $otherAdmin)
            ->assertTableActionHidden('impersonate', $this->admin);
    }

    public function test_the_row_action_is_not_offered_for_deactivated_accounts(): void
    {
        $gone = UserModel::factory()->create();
        $gone->delete();

        $list = Livewire::test(ListUsers::class)->set('tableFilters.trashed.value', '1');

        $this->assertTrue($this->impersonateAction($list, $gone)->isHidden());
    }

    public function test_the_row_action_is_not_offered_to_someone_without_the_permission(): void
    {
        $editor = $this->editorWithPermissions(['admin.access', 'users.view_any', 'users.view']);
        $this->actingAs($editor);

        $list = Livewire::test(ListUsers::class);

        $this->assertTrue($this->impersonateAction($list, $this->member)->isHidden());
    }

    public function test_the_header_action_follows_the_same_rule(): void
    {
        Livewire::test(EditUser::class, ['record' => $this->member->getKey()])
            ->assertActionVisible('impersonate');

        Livewire::test(EditUser::class, ['record' => UserModel::factory()->admin()->create()->getKey()])
            ->assertActionHidden('impersonate');
    }

    public function test_confirming_without_the_box_starts_read_only_and_goes_to_the_site(): void
    {
        Livewire::test(ListUsers::class)
            ->callAction(TestAction::make('impersonate')->table($this->member), ['simulate' => false])
            ->assertHasNoActionErrors()
            ->assertRedirect(route('home'));

        $entry = ImpersonationLogEntryModel::query()->sole();
        $this->assertSame($this->admin->id, $entry->impersonator_id);
        $this->assertSame($this->member->id, $entry->target_id);
        $this->assertSame(ImpersonationMode::ReadOnly, $entry->mode);
    }

    public function test_the_box_starts_in_simulate_mode(): void
    {
        Livewire::test(EditUser::class, ['record' => $this->member->getKey()])
            ->callAction('impersonate', ['simulate' => true])
            ->assertRedirect(route('home'));

        $this->assertSame(ImpersonationMode::Simulate, ImpersonationLogEntryModel::query()->sole()->mode);
    }

    public function test_the_box_is_unticked_by_default(): void
    {
        Livewire::test(EditUser::class, ['record' => $this->member->getKey()])
            ->mountAction('impersonate')
            ->assertSchemaStateSet(['simulate' => false]);
    }

    /**
     * @param  Testable<*>  $component
     */
    private function impersonateAction(Testable $component, UserModel $record): Action
    {
        $instance = $component->instance();
        $this->assertInstanceOf(HasTable::class, $instance);
        $action = $instance->getTable()->getAction('impersonate');
        $this->assertInstanceOf(Action::class, $action);

        return $action->record($record);
    }
}
