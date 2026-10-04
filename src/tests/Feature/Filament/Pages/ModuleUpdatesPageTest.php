<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Updates\DTOs\UpdateCheckResultDTO;
use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Filament\Pages\ModuleUpdatesPage;
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
use Tests\TestCase;

final class ModuleUpdatesPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_page_is_forbidden_for_editors(): void
    {
        $this->actingAs(UserModel::factory()->editor()->create());

        $this->get(ModuleUpdatesPage::getUrl())->assertForbidden();
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
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->pendingUpdate();

        Livewire::test(ModuleUpdatesPage::class)
            ->call('updateModule', 'event-registrations')
            ->assertSet('queuedModules', ['event-registrations'])
            ->assertNotified(__('filament.updates.modules.notifications.update_queued', ['module' => 'event-registrations']));

        Queue::assertPushed(UpdateModuleJob::class, fn (UpdateModuleJob $job): bool => $job->moduleName === 'event-registrations');
    }

    public function test_update_all_queues_one_job_per_pending_update(): void
    {
        Queue::fake();
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
