<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Impersonation;

use App\Domain\Impersonation\Enums\ImpersonationEndReason;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Filament\Resources\UserResource\Pages\EditUser;
use App\Filament\Resources\UserResource\RelationManagers\ImpersonationsAsImpersonatorRelationManager;
use App\Filament\Resources\UserResource\RelationManagers\ImpersonationsAsTargetRelationManager;
use App\Infrastructure\Persistence\Eloquent\Models\ImpersonationLogEntryModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

/**
 * The read-only impersonation history on the user page (core#578).
 */
final class ImpersonationHistoryTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    private UserModel $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = UserModel::factory()->admin()->create();
        $this->actingAs($this->admin);
    }

    public function test_the_account_page_lists_who_viewed_it_newest_first(): void
    {
        $target = UserModel::factory()->create();
        $older = $this->entry($target, ['started_at' => now()->subDays(2)]);
        $newer = $this->entry($target, ['started_at' => now()->subDay(), 'mode' => ImpersonationMode::Simulate]);
        $other = $this->entry(UserModel::factory()->create());

        Livewire::test(ImpersonationsAsTargetRelationManager::class, ['ownerRecord' => $target, 'pageClass' => EditUser::class])
            ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
            ->assertCanNotSeeTableRecords([$other])
            ->assertSee($this->admin->name);
    }

    public function test_the_administrator_page_lists_who_they_viewed(): void
    {
        $member = UserModel::factory()->create(['name' => 'Persona Vista', 'display_name' => null]);
        $mine = $this->entry($member);
        $other = $this->entry($member, [], UserModel::factory()->admin()->create());

        Livewire::test(ImpersonationsAsImpersonatorRelationManager::class, ['ownerRecord' => $this->admin, 'pageClass' => EditUser::class])
            ->assertCanSeeTableRecords([$mine])
            ->assertCanNotSeeTableRecords([$other])
            ->assertSee('Persona Vista');
    }

    public function test_it_shows_mode_end_and_reason(): void
    {
        $target = UserModel::factory()->create();
        $closed = $this->entry($target, ['ended_at' => now(), 'ended_reason' => ImpersonationEndReason::Revoked]);

        Livewire::test(ImpersonationsAsTargetRelationManager::class, ['ownerRecord' => $target, 'pageClass' => EditUser::class])
            ->assertCanSeeTableRecords([$closed])
            ->assertSee(__('filament.impersonation.history.modes.read_only'))
            ->assertSee(__('filament.impersonation.history.reasons.revoked'));
    }

    public function test_an_entry_without_end_says_so(): void
    {
        $target = UserModel::factory()->create();
        $this->entry($target);

        Livewire::test(ImpersonationsAsTargetRelationManager::class, ['ownerRecord' => $target, 'pageClass' => EditUser::class])
            ->assertSee(__('filament.impersonation.history.open'));
    }

    public function test_it_is_read_only_and_fits_the_narrow_panel(): void
    {
        $target = UserModel::factory()->create();
        $this->entry($target);

        $component = Livewire::test(ImpersonationsAsTargetRelationManager::class, ['ownerRecord' => $target, 'pageClass' => EditUser::class]);
        $instance = $component->instance();
        $this->assertInstanceOf(ImpersonationsAsTargetRelationManager::class, $instance);
        $table = $instance->getTable();

        $this->assertTrue($instance->isReadOnly());
        $hidden = array_filter($table->getColumns(), static fn ($column): bool => $column->getVisibleFrom() !== null);
        $this->assertNotEmpty($hidden);
        $this->assertNotNull($table->getAction('rowDetails'));
    }

    public function test_only_who_holds_the_permission_sees_the_history(): void
    {
        $target = UserModel::factory()->create();

        $this->assertTrue(ImpersonationsAsTargetRelationManager::canViewForRecord($target, EditUser::class));
        $this->assertTrue(ImpersonationsAsImpersonatorRelationManager::canViewForRecord($this->admin, EditUser::class));

        $editor = $this->editorWithPermissions(['admin.access', 'users.view_any', 'users.view']);
        $this->actingAs($editor);

        $this->assertFalse(ImpersonationsAsTargetRelationManager::canViewForRecord($target, EditUser::class));
        $this->assertFalse(ImpersonationsAsImpersonatorRelationManager::canViewForRecord($this->admin, EditUser::class));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entry(UserModel $target, array $attributes = [], ?UserModel $impersonator = null): ImpersonationLogEntryModel
    {
        return ImpersonationLogEntryModel::query()->create([
            'impersonator_id' => ($impersonator ?? $this->admin)->id,
            'target_id' => $target->id,
            'mode' => ImpersonationMode::ReadOnly,
            'started_at' => now()->subHour(),
            ...$attributes,
        ]);
    }
}
