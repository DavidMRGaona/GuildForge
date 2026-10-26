<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Updates\Jobs;

use App\Application\Updates\DTOs\ModuleUpdateResultDTO;
use App\Application\Updates\Services\ModuleUpdaterInterface;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Domain\Updates\Exceptions\UpdateException;
use App\Infrastructure\Authorization\Services\RoleAuditContext;
use App\Infrastructure\Updates\Jobs\UpdateModuleJob;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\ModuleUpdateHistoryModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

final class UpdateModuleJobTest extends TestCase
{
    use RefreshDatabase; // migrates before Artisan is mocked

    public function test_handle_updates_module_and_restarts_queue_workers(): void
    {
        $updater = Mockery::mock(ModuleUpdaterInterface::class);
        $updater->shouldReceive('update')->once()->andReturn($this->updateResult(UpdateStatus::Completed));
        Artisan::shouldReceive('call')->once()->with('queue:restart');

        (new UpdateModuleJob('game-tables'))->handle($updater, $this->app->make(RoleAuditContext::class));
    }

    public function test_the_update_runs_on_behalf_of_who_requested_it(): void
    {
        $actorId = '019a2b3c-4d5e-7f60-8a9b-0c1d2e3f4a5b';
        $context = $this->app->make(RoleAuditContext::class);
        $updater = Mockery::mock(ModuleUpdaterInterface::class);
        $updater->shouldReceive('update')->once()->andReturnUsing(function () use ($context, $actorId): ModuleUpdateResultDTO {
            // What the role history and the finish-update process get while the update runs
            $this->assertSame($actorId, $context->actorId());

            return $this->updateResult(UpdateStatus::Completed);
        });
        Artisan::shouldReceive('call')->once()->with('queue:restart');

        (new UpdateModuleJob('game-tables', $actorId))->handle($updater, $context);

        $this->assertNull($context->actorId());
    }

    public function test_a_job_queued_before_it_recorded_the_requester_still_runs(): void
    {
        // Unserializing skips the constructor: a job queued by the previous release has no requester
        $class = new \ReflectionClass(UpdateModuleJob::class);
        $job = $class->newInstanceWithoutConstructor();
        $class->getProperty('moduleName')->setValue($job, 'game-tables');

        $context = $this->app->make(RoleAuditContext::class);
        $updater = Mockery::mock(ModuleUpdaterInterface::class);
        $updater->shouldReceive('update')->once()->andReturnUsing(function () use ($context): ModuleUpdateResultDTO {
            $this->assertNull($context->actorId());

            return $this->updateResult(UpdateStatus::Completed);
        });
        Artisan::shouldReceive('call')->once()->with('queue:restart');

        $job->handle($updater, $this->app->make(RoleAuditContext::class));
    }

    public function test_an_update_that_throws_is_recorded_as_failed_and_workers_restart(): void
    {
        $updater = Mockery::mock(ModuleUpdaterInterface::class);
        $updater->shouldReceive('update')->andThrow(UpdateException::lockAcquisitionFailed('game-tables'));
        Artisan::shouldReceive('call')->once()->with('queue:restart');

        try {
            (new UpdateModuleJob('game-tables'))->handle($updater, $this->app->make(RoleAuditContext::class));
            $this->fail('Expected the exception to propagate');
        } catch (UpdateException) {
        }

        // The admin page follows updates through the history; without a row it would wait forever
        $history = ModuleUpdateHistoryModel::query()->where('module_name', 'game-tables')->first();
        $this->assertNotNull($history);
        $this->assertSame(UpdateStatus::Failed, $history->status);
        $this->assertNotNull($history->completed_at);
    }

    public function test_job_is_unique_per_module(): void
    {
        Queue::fake();

        UpdateModuleJob::dispatch('game-tables');
        UpdateModuleJob::dispatch('game-tables');
        UpdateModuleJob::dispatch('tournaments');

        // The second update of the same module is dropped while the first one holds the lock
        Queue::assertPushed(UpdateModuleJob::class, 2);
        $this->assertSame('module-update:game-tables', (new UpdateModuleJob('game-tables'))->uniqueId());
    }

    public function test_queue_retry_after_exceeds_the_job_timeout(): void
    {
        $timeout = (new UpdateModuleJob('game-tables'))->timeout;

        // Otherwise a long update is handed to the worker a second time while it is still running
        $this->assertGreaterThan($timeout, config('queue.connections.redis.retry_after'));
        $this->assertGreaterThan($timeout, config('queue.connections.database.retry_after'));
    }

    private function updateResult(UpdateStatus $status): ModuleUpdateResultDTO
    {
        return new ModuleUpdateResultDTO(
            moduleName: 'game-tables',
            fromVersion: '1.0.0',
            toVersion: '1.0.1',
            status: $status,
            migrationsRun: [],
            seedersRun: [],
            errorMessage: null,
            backupPath: null,
            historyId: 'history-id',
        );
    }
}
