<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Services\SettingsServiceInterface;
use App\Application\Updates\DTOs\BlockedReleaseDTO;
use App\Application\Updates\DTOs\UpdateCheckResultDTO;
use App\Application\Updates\DTOs\UpdatePreviewDTO;
use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\Application\Updates\Services\ModuleUpdaterInterface;
use App\Domain\Modules\Enums\RequirementType;
use App\Domain\Modules\ValueObjects\CompatibilityIssue;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Filament\Pages\ModuleUpdatesPage;
use App\Filament\Pages\SiteSettings;
use App\Infrastructure\Persistence\Eloquent\Models\ModuleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Updates\Jobs\UpdateModuleJob;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleUpdateHistoryModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Mockery;
use Tests\Support\Authorization\CreatesUsersWithPermissions;
use Tests\TestCase;

final class ModuleUpdatesPageTest extends TestCase
{
    use CreatesUsersWithPermissions;
    use LazilyRefreshDatabase;

    public function test_page_is_forbidden_for_editors(): void
    {
        $this->actingAs(UserModel::factory()->editor()->create());

        $this->get(ModuleUpdatesPage::getUrl())->assertForbidden();
    }

    public function test_page_is_accessible_with_the_apply_updates_permission(): void
    {
        $this->actingAs($this->editorWithPermissions(['updates.apply']));

        $this->get(ModuleUpdatesPage::getUrl())->assertOk();
    }

    public function test_page_warns_that_updates_need_maintenance_mode(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ModuleUpdatesPage::class)
            ->assertSee(__('filament.updates.modules.maintenance_required.title'));
    }

    public function test_maintenance_warning_links_to_the_maintenance_tab(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ModuleUpdatesPage::class)
            ->assertSeeHtml('href="'.SiteSettings::getUrl(['tab' => 'settings-maintenance-tab']).'"');
    }

    public function test_page_does_not_warn_while_maintenance_mode_is_enabled(): void
    {
        $this->enableMaintenance();
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ModuleUpdatesPage::class)
            ->assertDontSee(__('filament.updates.modules.maintenance_required.title'));
    }

    public function test_page_shows_persisted_pending_updates_without_checking_github(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->pendingUpdate();
        $checker = Mockery::mock(ModuleUpdateCheckerInterface::class);
        $checker->shouldNotReceive('checkAll');
        $this->app->instance(ModuleUpdateCheckerInterface::class, $checker);

        Livewire::test(ModuleUpdatesPage::class)
            ->assertSee('1.0.9-beta')
            ->assertSee('event-registrations');
    }

    public function test_page_lists_modules_without_repository(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        ModuleModel::factory()->enabled()->create(['name' => 'security-test', 'source_owner' => null, 'source_repo' => null]);

        Livewire::test(ModuleUpdatesPage::class)
            ->assertSet('modulesWithoutSource', ['security-test'])
            ->assertSee(__('filament.updates.modules.without_source.title'));
    }

    public function test_check_shows_github_errors_and_bypasses_cache(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $checker = Mockery::mock(ModuleUpdateCheckerInterface::class);
        $checker->shouldReceive('checkAll')->with(true)->once()->andReturn(new UpdateCheckResultDTO(
            new Collection,
            ['game-tables' => "GitHub request for 'o/r' failed: HTTP 403"],
            [],
        ));
        $this->app->instance(ModuleUpdateCheckerInterface::class, $checker);

        Livewire::test(ModuleUpdatesPage::class)
            ->call('checkForUpdates')
            ->assertSet('checkErrors', ['game-tables' => "GitHub request for 'o/r' failed: HTTP 403"])
            ->assertSee('HTTP 403')
            ->assertNotified(__('filament.updates.modules.notifications.check_failed'));
    }

    public function test_navigation_badge_counts_pending_updates(): void
    {
        $this->pendingUpdate();
        ModuleModel::factory()->enabled()->create(['name' => 'tournaments', 'version' => '1.0.9-beta', 'latest_available_version' => null]);

        $this->assertSame('1', ModuleUpdatesPage::getNavigationBadge());
    }

    public function test_update_module_queues_a_job(): void
    {
        Queue::fake();
        $this->enableMaintenance();
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->pendingUpdate();

        Livewire::test(ModuleUpdatesPage::class)
            ->call('updateModule', 'event-registrations')
            ->assertSet('queuedModules', ['event-registrations'])
            ->assertNotified(__('filament.updates.modules.notifications.update_queued', ['module' => 'event-registrations']));

        Queue::assertPushed(UpdateModuleJob::class, fn (UpdateModuleJob $job): bool => $job->moduleName === 'event-registrations');
    }

    public function test_update_module_is_refused_without_maintenance_mode(): void
    {
        Queue::fake();
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->pendingUpdate();

        Livewire::test(ModuleUpdatesPage::class)
            ->call('updateModule', 'event-registrations')
            ->assertSet('queuedModules', [])
            ->assertNotified(__('filament.updates.modules.notifications.maintenance_required'));

        Queue::assertNothingPushed();
    }

    public function test_update_all_is_refused_without_maintenance_mode(): void
    {
        Queue::fake();
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->pendingUpdate();

        Livewire::test(ModuleUpdatesPage::class)
            ->assertActionDisabled('updateAll')
            ->call('updateAllModules')
            ->assertNotified(__('filament.updates.modules.notifications.maintenance_required'));

        Queue::assertNothingPushed();
    }

    public function test_update_all_queues_one_job_per_pending_update(): void
    {
        Queue::fake();
        $this->enableMaintenance();
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->pendingUpdate();
        ModuleModel::factory()->enabled()->create([
            'name' => 'tournaments',
            'version' => '1.0.8-beta',
            'source_owner' => 'o',
            'source_repo' => 'tournaments',
            'latest_available_version' => '1.0.10-beta',
        ]);

        Livewire::test(ModuleUpdatesPage::class)->call('updateAllModules');

        Queue::assertPushed(UpdateModuleJob::class, 2);
    }

    public function test_poll_reports_finished_updates_and_stops_following_them(): void
    {
        Queue::fake();
        $this->enableMaintenance();
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->pendingUpdate();
        $page = Livewire::test(ModuleUpdatesPage::class)->call('updateModule', 'event-registrations');

        ModuleUpdateHistoryModel::create([
            'id' => (string) Str::uuid(),
            'module_name' => 'event-registrations',
            'from_version' => '1.0.8-beta',
            'to_version' => '1.0.9-beta',
            'status' => UpdateStatus::Completed,
            'started_at' => now()->addSecond(),
        ]);
        ModuleModel::query()->where('name', 'event-registrations')->update(['version' => '1.0.9-beta', 'latest_available_version' => null]);

        $page->call('pollUpdates')
            ->assertSet('queuedModules', [])
            ->assertSet('availableUpdates', [])
            ->assertNotified(__('filament.updates.modules.notifications.update_success', ['module' => 'event-registrations', 'version' => '1.0.9-beta']));
    }

    public function test_poll_keeps_following_updates_that_are_still_running(): void
    {
        Queue::fake();
        $this->enableMaintenance();
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->pendingUpdate();
        $page = Livewire::test(ModuleUpdatesPage::class)->call('updateModule', 'event-registrations');

        ModuleUpdateHistoryModel::create([
            'id' => (string) Str::uuid(),
            'module_name' => 'event-registrations',
            'from_version' => '1.0.8-beta',
            'to_version' => '1.0.9-beta',
            'status' => UpdateStatus::Downloading,
            'started_at' => now()->addSecond(),
        ]);

        $page->call('pollUpdates')->assertSet('queuedModules', ['event-registrations']);
    }

    public function test_poll_reports_an_update_that_started_before_the_page_queued_it(): void
    {
        Queue::fake();
        $this->enableMaintenance();
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->pendingUpdate();
        ModuleUpdateHistoryModel::create([
            'id' => (string) Str::uuid(),
            'module_name' => 'event-registrations',
            'from_version' => '1.0.8-beta',
            'to_version' => '1.0.9-beta',
            'status' => UpdateStatus::Downloading,
            'started_at' => now()->subMinute(),
        ]);
        $page = Livewire::test(ModuleUpdatesPage::class)->call('updateModule', 'event-registrations');

        ModuleUpdateHistoryModel::query()->update(['status' => UpdateStatus::Completed, 'completed_at' => now()->addSecond()]);

        $page->call('pollUpdates')->assertSet('queuedModules', []);
    }

    public function test_poll_gives_up_on_updates_that_never_report_back(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(ModuleUpdatesPage::class)
            ->set('queuedModules', ['event-registrations'])
            ->set('queuedSince', now()->subMinutes(20)->toIso8601String())
            ->call('pollUpdates')
            ->assertSet('queuedModules', [])
            ->assertNotified(__('filament.updates.modules.notifications.update_lost', ['module' => 'event-registrations']));
    }

    public function test_history_shows_translated_status_and_flags_only_failures(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $ok = $this->historyRow(UpdateStatus::Completed, null);
        $failed = $this->historyRow(UpdateStatus::Failed, 'HTTP 403');

        $page = Livewire::test(ModuleUpdatesPage::class)
            ->assertTableColumnFormattedStateSet('status', UpdateStatus::Completed->label(), $ok);

        $column = $page->instance()->getTable()->getColumn('error_message');
        $column->record($ok);
        $this->assertNull($column->getIcon($column->getState()), 'A successful update must not show any error icon');
        $column->record($failed);
        $this->assertSame('heroicon-o-exclamation-triangle', $column->getIcon($column->getState()));
    }

    private function historyRow(UpdateStatus $status, ?string $error): ModuleUpdateHistoryModel
    {
        return ModuleUpdateHistoryModel::create([
            'id' => (string) Str::uuid(),
            'module_name' => 'announcements',
            'from_version' => '1.0.6-beta',
            'to_version' => '1.0.7-beta',
            'status' => $status,
            'error_message' => $error,
            'started_at' => now(),
            'completed_at' => now(),
        ]);
    }

    public function test_details_button_opens_the_preview_action(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->pendingUpdate();

        Livewire::test(ModuleUpdatesPage::class)
            ->assertSeeHtml("mountAction('preview', { module: 'event-registrations' })");
    }

    public function test_preview_shows_versions_and_the_release_notes(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->previewReturns("## Cambios\n\n- Arreglados los correos duplicados");

        Livewire::test(ModuleUpdatesPage::class)
            ->mountAction('preview', ['module' => 'event-registrations'])
            ->assertActionMounted('preview')
            ->assertSee('v1.0.8-beta')
            ->assertSee('v1.0.9-beta')
            ->assertSeeHtml('<li>Arreglados los correos duplicados</li>')
            ->assertSee(__('filament.updates.modules.preview.compatible'));
    }

    public function test_page_lists_persisted_blocked_releases_without_an_update_button(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        ModuleModel::factory()->enabled()->create([
            'name' => 'announcements',
            'version' => '1.1.0',
            'source_owner' => 'o',
            'source_repo' => 'announcements',
            'latest_blocked_version' => '2.0.0',
            'latest_blocked_reason' => [(new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED))->toArray()],
        ]);

        Livewire::test(ModuleUpdatesPage::class)
            ->assertSee(__('filament.updates.modules.blocked.heading'))
            ->assertSee('2.0.0 disponible, requiere core ^3.0')
            ->assertDontSeeHtml("updateModule('announcements')");
        $this->assertNull(ModuleUpdatesPage::getNavigationBadge());
    }

    public function test_check_shows_the_blocked_releases_it_found(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $checker = Mockery::mock(ModuleUpdateCheckerInterface::class);
        $checker->shouldReceive('checkAll')->with(true)->once()->andReturn(new UpdateCheckResultDTO(new Collection, [], [], [
            new BlockedReleaseDTO('announcements', 'Anuncios', '1.1.0', '2.0.0', [
                new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED),
            ]),
        ]));
        $this->app->instance(ModuleUpdateCheckerInterface::class, $checker);

        Livewire::test(ModuleUpdatesPage::class)
            ->call('checkForUpdates')
            ->assertCount('blockedReleases', 1)
            ->assertSee('2.0.0 disponible, requiere core ^3.0');
    }

    public function test_preview_shows_why_a_release_is_not_compatible(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $updater = Mockery::mock(ModuleUpdaterInterface::class);
        $updater->shouldReceive('preview')->andReturn(new UpdatePreviewDTO(
            moduleName: 'announcements',
            fromVersion: '1.1.0',
            toVersion: '2.0.0',
            pendingMigrations: [],
            newSeeders: [],
            changelog: '',
            isMajorUpdate: true,
            coreCompatible: false,
            coreRequirement: '^3.0',
            downloadUrl: null,
            downloadSize: null,
            compatibilityIssues: [(new CompatibilityIssue(RequirementType::Core, '^3.0', '2.6.0', CompatibilityIssue::UNSATISFIED))->toArray()],
        ));
        $this->app->instance(ModuleUpdaterInterface::class, $updater);

        Livewire::test(ModuleUpdatesPage::class)
            ->mountAction('preview', ['module' => 'announcements'])
            ->assertSee(__('filament.updates.modules.preview.incompatible'))
            ->assertSee('Requiere core ^3.0')
            ->assertSee('requiere core ^3.0, instalado 2.6.0');
    }

    public function test_preview_does_not_render_html_from_the_release_notes(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->previewReturns("Notas <script>alert('x')</script>");

        Livewire::test(ModuleUpdatesPage::class)
            ->mountAction('preview', ['module' => 'event-registrations'])
            ->assertSee('Notas')
            ->assertDontSeeHtml("<script>alert('x')</script>");
    }

    public function test_preview_shows_why_the_details_could_not_be_loaded(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $updater = Mockery::mock(ModuleUpdaterInterface::class);
        $updater->shouldReceive('preview')->andThrow(UpdateException::githubRequestFailed('o/event-registrations', 'HTTP 403'));
        $this->app->instance(ModuleUpdaterInterface::class, $updater);

        Livewire::test(ModuleUpdatesPage::class)
            ->mountAction('preview', ['module' => 'event-registrations'])
            ->assertActionMounted('preview')
            ->assertSee('HTTP 403');
    }

    private function previewReturns(string $changelog): void
    {
        $updater = Mockery::mock(ModuleUpdaterInterface::class);
        $updater->shouldReceive('preview')->andReturn(new UpdatePreviewDTO(
            moduleName: 'event-registrations',
            fromVersion: '1.0.8-beta',
            toVersion: '1.0.9-beta',
            pendingMigrations: [],
            newSeeders: [],
            changelog: $changelog,
            isMajorUpdate: false,
            coreCompatible: true,
            coreRequirement: null,
            downloadUrl: null,
            downloadSize: null,
        ));
        $this->app->instance(ModuleUpdaterInterface::class, $updater);
    }

    private function enableMaintenance(): void
    {
        app(SettingsServiceInterface::class)->set('maintenance_enabled', '1');
    }

    private function pendingUpdate(): void
    {
        ModuleModel::factory()->enabled()->create([
            'name' => 'event-registrations',
            'version' => '1.0.8-beta',
            'source_owner' => 'o',
            'source_repo' => 'event-registrations',
            'latest_available_version' => '1.0.9-beta',
        ]);
    }
}
