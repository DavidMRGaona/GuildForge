<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Updates\Jobs;

use App\Application\Updates\DTOs\ModuleUpdateResultDTO;
use App\Application\Updates\Services\ModuleUpdaterInterface;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Infrastructure\Updates\Jobs\UpdateModuleJob;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Support\Facades\Artisan;
use Mockery;
use Tests\TestCase;

final class UpdateModuleJobTest extends TestCase
{
    public function test_handle_updates_module_and_restarts_queue_workers(): void
    {
        $updater = Mockery::mock(ModuleUpdaterInterface::class);
        $updater->shouldReceive('update')->once()->andReturn($this->updateResult(UpdateStatus::Completed));
        Artisan::shouldReceive('call')->once()->with('queue:restart');

        (new UpdateModuleJob('game-tables'))->handle($updater);
    }

    public function test_workers_restart_even_when_the_update_throws(): void
    {
        $updater = Mockery::mock(ModuleUpdaterInterface::class);
        $updater->shouldReceive('update')->andThrow(new \RuntimeException('boom'));
        Artisan::shouldReceive('call')->once()->with('queue:restart');

        $this->expectException(\RuntimeException::class);

        (new UpdateModuleJob('game-tables'))->handle($updater);
    }

    public function test_job_is_unique_per_module(): void
    {
        $job = new UpdateModuleJob('game-tables');

        $this->assertInstanceOf(ShouldBeUnique::class, $job);
        $this->assertSame('module-update:game-tables', $job->uniqueId());
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
