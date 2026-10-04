<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use App\Domain\Updates\Enums\UpdateStatus;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\CoreUpdateHistoryModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * The container entrypoint records each deployment: "start" before migrating,
 * "finish" once the container is ready, "fail" when a step breaks the boot.
 */
final class RecordDeploymentCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string FIRST = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const string SECOND = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function test_the_first_deployment_has_no_previous_commit(): void
    {
        $this->deploy(self::FIRST, 'start');

        $row = CoreUpdateHistoryModel::query()->sole();
        $this->assertSame('', $row->git_commit_before);
        $this->assertSame(self::FIRST, $row->git_commit_after);
        $this->assertSame(UpdateStatus::Applying, $row->status);
    }

    public function test_a_deployment_starts_from_the_previous_commit_and_finishes(): void
    {
        $this->deploy(self::FIRST, 'start');
        $this->deploy(self::FIRST, 'finish');

        $this->deploy(self::SECOND, 'start');
        $this->deploy(self::SECOND, 'finish');

        $latest = CoreUpdateHistoryModel::query()->where('git_commit_after', self::SECOND)->sole();
        $this->assertSame(self::FIRST, $latest->git_commit_before);
        $this->assertSame(UpdateStatus::Completed, $latest->status);
        $this->assertSame(2, CoreUpdateHistoryModel::query()->count());
    }

    public function test_restarting_the_same_deployment_records_nothing_new(): void
    {
        $this->deploy(self::FIRST, 'start');
        $this->deploy(self::FIRST, 'finish');

        $this->deploy(self::FIRST, 'start');
        $this->deploy(self::FIRST, 'finish');

        $this->assertSame(1, CoreUpdateHistoryModel::query()->count());
        $this->assertSame(UpdateStatus::Completed, CoreUpdateHistoryModel::query()->sole()->status);
    }

    public function test_a_failed_boot_keeps_its_error_and_a_retry_reuses_the_row(): void
    {
        $this->deploy(self::FIRST, 'start');
        $this->artisan('core:record-deployment', ['step' => 'fail', '--error' => 'Migrations failed'])->assertExitCode(0);

        $row = CoreUpdateHistoryModel::query()->sole();
        $this->assertSame(UpdateStatus::Failed, $row->status);
        $this->assertSame('Migrations failed', $row->error_message);

        $this->deploy(self::FIRST, 'start');
        $this->deploy(self::FIRST, 'finish');

        $row = CoreUpdateHistoryModel::query()->sole();
        $this->assertSame(UpdateStatus::Completed, $row->status);
        $this->assertNull($row->error_message);
    }

    public function test_an_unknown_commit_is_not_recorded_and_does_not_break_the_boot(): void
    {
        config(['updates.core.commit' => null]);
        $this->app->instance(\App\Application\Updates\Services\CoreVersionServiceInterface::class, new class implements \App\Application\Updates\Services\CoreVersionServiceInterface
        {
            public function getCurrentVersion(): \App\Domain\Modules\ValueObjects\ModuleVersion
            {
                return \App\Domain\Modules\ValueObjects\ModuleVersion::fromString('1.0.0');
            }

            public function getCurrentCommit(): string
            {
                return 'unknown';
            }

            public function satisfies(string $constraint): bool
            {
                return true;
            }
        });

        $this->artisan('core:record-deployment', ['step' => 'start'])->assertExitCode(0);

        $this->assertSame(0, CoreUpdateHistoryModel::query()->count());
    }

    public function test_an_invalid_step_fails(): void
    {
        config(['updates.core.commit' => self::FIRST]);

        $this->artisan('core:record-deployment', ['step' => 'restart'])->assertExitCode(1);
    }

    private function deploy(string $commit, string $step): void
    {
        config(['updates.core.commit' => $commit]);
        $this->artisan('core:record-deployment', ['step' => $step])->assertExitCode(0);
        $this->travel(1)->minutes();
    }
}
