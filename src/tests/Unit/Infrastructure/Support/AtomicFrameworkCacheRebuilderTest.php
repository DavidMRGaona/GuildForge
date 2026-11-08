<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Support;

use App\Infrastructure\Support\AtomicFrameworkCacheRebuilder;
use App\Infrastructure\Support\FrameworkCache;
use App\Infrastructure\Updates\Services\ProcessModulePostUpdateRunner;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class AtomicFrameworkCacheRebuilderTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/atomic-cache-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->dir);

        foreach (FrameworkCache::cases() as $cache) {
            $_SERVER[$cache->pathVariable()] = $this->dir.'/'.$cache->value.'.php';
        }
    }

    protected function tearDown(): void
    {
        foreach (FrameworkCache::cases() as $cache) {
            unset($_SERVER[$cache->pathVariable()]);
        }

        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_it_builds_the_cache_beside_the_old_one_in_a_fresh_process_and_swaps_it_in(): void
    {
        $target = $this->dir.'/routes.php';
        File::put($target, '<?php return "old";');
        $builtAt = null;

        Process::fake(function (PendingProcess $process) use (&$builtAt): mixed {
            $builtAt = $process->environment['APP_ROUTES_CACHE'];
            File::put($builtAt, '<?php return "new";');

            return Process::result();
        });

        $this->assertTrue($this->rebuilder()->rebuild(FrameworkCache::Routes));

        Process::assertRan(fn (PendingProcess $process): bool => $process->command === [ProcessModulePostUpdateRunner::phpBinary(), base_path('artisan'), 'route:cache', '--no-interaction']);
        $this->assertIsString($builtAt);
        $this->assertNotSame($target, $builtAt);
        $this->assertSame($this->dir, dirname($builtAt), 'The new file must sit beside the old one, so the rename is atomic');
        $this->assertSame('<?php return "new";', File::get($target));
        $this->assertFileDoesNotExist($builtAt);
    }

    public function test_the_old_cache_stays_readable_while_the_new_one_is_built(): void
    {
        $target = $this->dir.'/config.php';
        File::put($target, '<?php return ["old" => true];');
        $seenDuringBuild = null;

        Process::fake(function (PendingProcess $process) use ($target, &$seenDuringBuild): mixed {
            $seenDuringBuild = File::get($target);
            File::put($process->environment['APP_CONFIG_CACHE'], '<?php return ["new" => true];');

            return Process::result();
        });

        $this->rebuilder()->rebuild(FrameworkCache::Config);

        $this->assertSame('<?php return ["old" => true];', $seenDuringBuild);
    }

    public function test_a_failed_build_drops_the_stale_cache_and_the_partial_file(): void
    {
        $target = $this->dir.'/events.php';
        File::put($target, '<?php return [];');
        $partial = null;
        $log = Log::spy();

        Process::fake(function (PendingProcess $process) use (&$partial): mixed {
            $partial = $process->environment['APP_EVENTS_CACHE'];
            File::put($partial, '<?php return [');

            return Process::result(errorOutput: 'Your configuration files are not serializable.', exitCode: 1);
        });

        $this->assertFalse($this->rebuilder()->rebuild(FrameworkCache::Events));

        // A stale cache hides what the module change added; without one the site reads the sources
        $this->assertFileDoesNotExist($target);
        $this->assertIsString($partial);
        $this->assertFileDoesNotExist($partial);
        $log->shouldHaveReceived('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $context['cache'] === 'events'
                && str_contains((string) $context['error'], 'not serializable'),
        );
    }

    public function test_a_build_that_writes_no_file_drops_the_stale_cache(): void
    {
        $target = $this->dir.'/routes.php';
        File::put($target, '<?php return "old";');
        $log = Log::spy();
        Process::fake();

        $this->assertFalse($this->rebuilder()->rebuild(FrameworkCache::Routes));

        $this->assertFileDoesNotExist($target);
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_it_leaves_no_file_behind_when_nothing_was_cached_and_the_build_fails(): void
    {
        Log::spy();
        Process::fake(['*' => Process::result(exitCode: 1)]);

        $this->assertFalse($this->rebuilder()->rebuild(FrameworkCache::Config));

        $this->assertSame([], File::files($this->dir));
    }

    public function test_the_real_config_cache_command_writes_a_loadable_cache_through_the_swap(): void
    {
        $target = $this->dir.'/config.php';
        File::put($target, '<?php return ["stale" => true];');

        $this->assertTrue($this->rebuilder()->rebuild(FrameworkCache::Config));

        /** @var array<string, mixed> $config */
        $config = require $target;
        $this->assertArrayNotHasKey('stale', $config);
        $this->assertArrayHasKey('app', $config);
        $this->assertSame([$target], array_map(strval(...), File::files($this->dir)));
    }

    private function rebuilder(): AtomicFrameworkCacheRebuilder
    {
        return $this->app->make(AtomicFrameworkCacheRebuilder::class);
    }
}
