<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Support;

use App\Infrastructure\Support\FrameworkCache;
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

    public function test_every_optimize_clear_task_has_a_decision_in_the_module_cache_refresh(): void
    {
        $command = $this->app->make(OptimizeClearCommand::class);

        /** @var array<string, string> $tasks */
        $tasks = (new ReflectionMethod($command, 'getOptimizeClearTasks'))->invoke($command);

        $handled = [
            // Rebuilt in place by AtomicFrameworkCacheRebuilder, never cleared first
            'config:clear' => FrameworkCache::Config,
            'route:clear' => FrameworkCache::Routes,
            'event:clear' => FrameworkCache::Events,
            // Recompiled in place (view:recompile), or cleared when the config is not cached
            'view:clear' => 'views',
            // FLUSHDB of the Redis cache database the tenants share
            'cache:clear' => 'never',
            // services.php and packages.php do not depend on modules; dropping them opens a gap
            'clear-compiled' => 'never',
        ];

        foreach ($tasks as $task) {
            $this->assertTrue(
                isset($handled[$task]) || in_array($task, FrameworkCacheCommands::CLEAR, true),
                "optimize:clear runs {$task}: decide how module:refresh-caches handles it",
            );
        }
    }

    public function test_every_command_is_registered(): void
    {
        $registered = array_keys(Artisan::all());

        foreach ([...FrameworkCacheCommands::CLEAR, 'view:recompile'] as $command) {
            $this->assertContains($command, $registered);
        }

        foreach (FrameworkCache::cases() as $cache) {
            $this->assertContains($cache->cacheCommand(), $registered);
        }
    }
}
