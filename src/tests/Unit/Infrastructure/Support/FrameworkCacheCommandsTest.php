<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Support;

use App\Infrastructure\Support\FrameworkCacheCommands;
use Illuminate\Foundation\Console\OptimizeClearCommand;
use Illuminate\Support\Facades\Artisan;
use ReflectionMethod;
use Tests\TestCase;

final class FrameworkCacheCommandsTest extends TestCase
{
    public function test_it_never_clears_the_data_cache(): void
    {
        $this->assertNotContains('cache:clear', FrameworkCacheCommands::CLEAR);
        $this->assertNotContains('optimize:clear', FrameworkCacheCommands::CLEAR);
    }

    public function test_it_covers_every_optimize_clear_task_except_the_data_cache(): void
    {
        $command = $this->app->make(OptimizeClearCommand::class);

        /** @var array<string, string> $tasks */
        $tasks = (new ReflectionMethod($command, 'getOptimizeClearTasks'))->invoke($command);

        foreach ($tasks as $task) {
            if ($task === 'cache:clear') {
                continue;
            }

            $this->assertContains(
                $task,
                FrameworkCacheCommands::CLEAR,
                "optimize:clear runs {$task}: add it to FrameworkCacheCommands::CLEAR",
            );
        }
    }

    public function test_every_command_is_registered(): void
    {
        $registered = array_keys(Artisan::all());

        foreach (FrameworkCacheCommands::CLEAR as $command) {
            $this->assertContains($command, $registered);
        }
    }
}
