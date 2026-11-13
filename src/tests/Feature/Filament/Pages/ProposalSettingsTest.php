<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Proposals\Services\ProposalSettingsServiceInterface;
use App\Domain\Proposals\Enums\ProposalAccessLevel;
use App\Filament\Clusters\SettingsCluster;
use App\Filament\Pages\Settings\ProposalSettings;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Livewire\Livewire;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class ProposalSettingsTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    public function test_it_lives_in_the_settings_cluster_with_its_own_slot(): void
    {
        $this->assertSame(SettingsCluster::class, ProposalSettings::getCluster());
        $this->assertSame(50, ProposalSettings::getNavigationSort());
        $this->assertSame('Propuestas', ProposalSettings::getNavigationLabel());
        $this->assertStringEndsWith('/admin/configuracion/propuestas', ProposalSettings::getUrl());
    }

    public function test_it_needs_the_settings_permission(): void
    {
        $this->actingAs(UserModel::factory()->editor()->create());
        $this->get(ProposalSettings::getUrl())->assertForbidden();

        $this->actingAs($this->editorWithPermissions(['settings.manage']));
        $this->get(ProposalSettings::getUrl())->assertOk()->assertSee('Permitir propuestas');
    }

    public function test_it_shows_proposals_off_by_default(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ProposalSettings::class)
            ->assertFormSet([
                'events_enabled' => false,
                'events_access_level' => ProposalAccessLevel::Registered->value,
                'events_show_proposer' => false,
                'articles_enabled' => false,
                'max_pending' => 3,
                'max_open_drafts' => 5,
                'rejected_retention_days' => 90,
                'submissions_per_hour' => 5,
            ]);
    }

    public function test_it_saves_every_setting(): void
    {
        RoleModel::query()->create(['name' => 'tesoreria', 'display_name' => 'Tesorería']);
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ProposalSettings::class)
            ->fillForm([
                'events_enabled' => true,
                'events_access_level' => ProposalAccessLevel::Roles->value,
                'events_roles' => ['tesoreria'],
                'events_show_proposer' => true,
                'articles_enabled' => true,
                'articles_access_level' => ProposalAccessLevel::Permission->value,
                'max_pending' => 2,
                'max_open_drafts' => 4,
                'rejected_retention_days' => 30,
                'submissions_per_hour' => 3,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $settings = app(ProposalSettingsServiceInterface::class)->settings();
        $this->assertTrue($settings->events->enabled);
        $this->assertSame(['tesoreria'], $settings->events->roles);
        $this->assertTrue($settings->events->showProposer);
        $this->assertSame(ProposalAccessLevel::Permission, $settings->articles->accessLevel);
        $this->assertSame([2, 4, 30, 3], [$settings->maxPending, $settings->maxOpenDrafts, $settings->rejectedRetentionDays, $settings->submissionsPerHour]);
    }

    public function test_the_roles_level_needs_existing_roles(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ProposalSettings::class)
            ->fillForm([
                'events_enabled' => true,
                'events_access_level' => ProposalAccessLevel::Roles->value,
                'events_roles' => [],
            ])
            ->call('save')
            ->assertHasFormErrors(['events_roles' => 'required']);

        Livewire::test(ProposalSettings::class)
            ->fillForm([
                'events_access_level' => ProposalAccessLevel::Roles->value,
                'events_roles' => ['forjado'],
            ])
            ->call('save')
            ->assertHasFormErrors(['events_roles']);
    }

    public function test_numbers_stay_within_their_bounds(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ProposalSettings::class)
            ->fillForm(['max_pending' => 0, 'submissions_per_hour' => 1000])
            ->call('save')
            ->assertHasFormErrors(['max_pending', 'submissions_per_hour']);
    }
}
