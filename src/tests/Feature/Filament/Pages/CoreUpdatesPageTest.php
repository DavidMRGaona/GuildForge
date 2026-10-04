<?php

declare(strict_types=1);

namespace Tests\Feature\Filament\Pages;

use App\Application\Updates\DTOs\CoreUpdateStatusDTO;
use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Filament\Pages\CoreUpdatesPage;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\CoreUpdateHistoryModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Mockery;
use Tests\TestCase;

final class CoreUpdatesPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string DEPLOYED = 'abc1234def4567890abc123def4567890abc1234';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['updates.core.commit' => self::DEPLOYED, 'updates.core.branch' => 'main']);
    }

    public function test_page_is_forbidden_for_editors(): void
    {
        $this->actingAs(UserModel::factory()->editor()->create());

        $this->get(CoreUpdatesPage::getUrl())->assertForbidden();
    }

    public function test_page_is_accessible_by_admins(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        $this->get(CoreUpdatesPage::getUrl())->assertOk();
    }

    public function test_page_shows_the_deployed_commit_and_branch(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());

        Livewire::test(CoreUpdatesPage::class)
            ->assertSee('abc1234')
            ->assertSee('main');
    }

    public function test_check_lists_the_commits_waiting_to_be_deployed(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->checkReturns($this->behindStatus());

        Livewire::test(CoreUpdatesPage::class)
            ->call('checkForUpdates')
            ->assertSee('fix: send one email on auto-confirmed registrations')
            ->assertSee(trans_choice('filament.updates.core.status.behind', 1, ['count' => 1, 'branch' => 'main']));
    }

    public function test_check_reports_an_up_to_date_deployment(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->checkReturns(new CoreUpdateStatusDTO(self::DEPLOYED, 'main', 0, self::DEPLOYED, [], false));

        Livewire::test(CoreUpdatesPage::class)
            ->call('checkForUpdates')
            ->assertSee(__('filament.updates.core.status.up_to_date', ['branch' => 'main']));
    }

    public function test_check_shows_why_it_failed_instead_of_up_to_date(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $checker = Mockery::mock(CoreUpdateCheckerInterface::class);
        $checker->shouldReceive('check')->andThrow(UpdateException::githubRequestFailed('DavidMRGaona/guildforge', 'HTTP 403'));
        $this->app->instance(CoreUpdateCheckerInterface::class, $checker);

        Livewire::test(CoreUpdatesPage::class)
            ->call('checkForUpdates')
            ->assertSee('HTTP 403')
            ->assertDontSee(__('filament.updates.core.status.up_to_date', ['branch' => 'main']))
            ->assertNotified(__('filament.updates.core.notifications.check_failed'));
    }

    public function test_the_last_check_is_shown_when_the_page_is_reopened(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        // Only the explicit check may query GitHub; reopening the page must not
        $checker = Mockery::mock(CoreUpdateCheckerInterface::class);
        $checker->shouldReceive('check')->once()->andReturn($this->behindStatus());
        $this->app->instance(CoreUpdateCheckerInterface::class, $checker);

        Livewire::test(CoreUpdatesPage::class)->call('checkForUpdates');

        Livewire::test(CoreUpdatesPage::class)
            ->assertSee('fix: send one email on auto-confirmed registrations');
    }

    public function test_a_remembered_check_for_another_deployment_is_ignored(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->checkReturns($this->behindStatus());
        Livewire::test(CoreUpdatesPage::class)->call('checkForUpdates');

        config(['updates.core.commit' => 'fff0000fff0000fff0000fff0000fff0000fff00']);

        Livewire::test(CoreUpdatesPage::class)
            ->assertDontSee('fix: send one email on auto-confirmed registrations');
    }

    public function test_page_shows_when_the_deployed_commit_was_deployed(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $this->travelTo(now()->setDate(2026, 10, 4)->setTime(19, 52));
        CoreUpdateHistoryModel::query()->create([
            'from_version' => '2.5.10', 'to_version' => '2.5.10',
            'git_commit_before' => 'fff0000fff0000fff0000fff0000fff0000fff00', 'git_commit_after' => self::DEPLOYED,
            'status' => UpdateStatus::Completed,
        ]);

        Livewire::test(CoreUpdatesPage::class)
            ->assertSee('04/10/2026 19:52');
    }

    public function test_deployment_history_lists_deployments_with_their_status(): void
    {
        $this->actingAs(UserModel::factory()->admin()->create());
        $failed = CoreUpdateHistoryModel::query()->create([
            'from_version' => '2.5.10', 'to_version' => '2.5.10',
            'git_commit_before' => self::DEPLOYED, 'git_commit_after' => 'ccc1111ccc1111ccc1111ccc1111ccc1111ccc11',
            'status' => UpdateStatus::Failed, 'error_message' => 'Migrations failed',
        ]);

        Livewire::test(CoreUpdatesPage::class)
            ->assertCanSeeTableRecords([$failed])
            ->assertSee('ccc1111')
            ->assertSee(UpdateStatus::Failed->label());
    }

    private function behindStatus(): CoreUpdateStatusDTO
    {
        return new CoreUpdateStatusDTO(self::DEPLOYED, 'main', 1, 'bbb', [
            ['sha' => 'bbbbbbbbbb', 'message' => 'fix: send one email on auto-confirmed registrations', 'date' => '2026-10-04T10:00:00Z', 'url' => 'https://github.com/o/r/commit/bbb'],
        ], false);
    }

    private function checkReturns(CoreUpdateStatusDTO $status): void
    {
        $checker = Mockery::mock(CoreUpdateCheckerInterface::class);
        $checker->shouldReceive('check')->andReturn($status);
        $this->app->instance(CoreUpdateCheckerInterface::class, $checker);
    }
}
