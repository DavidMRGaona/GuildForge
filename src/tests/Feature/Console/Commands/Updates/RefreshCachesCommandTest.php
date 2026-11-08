<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use App\Console\Commands\Updates\RefreshCachesCommand;
use App\Infrastructure\Support\FrameworkCache;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

final class RefreshCachesCommandTest extends TestCase
{
    private const array CLEARED = ['icons:clear', 'filament:optimize-clear'];

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = sys_get_temp_dir().'/refresh-caches-'.bin2hex(random_bytes(6));
        File::ensureDirectoryExists($this->dir);

        foreach (FrameworkCache::cases() as $cache) {
            $_SERVER[$cache->pathVariable()] = $this->dir.'/'.$cache->value.'.php';
            File::put($this->dir.'/'.$cache->value.'.php', '<?php return [];');
        }

        // Each child writes the cache it was asked for at the path it was given
        Process::fake(function (PendingProcess $process): mixed {
            foreach (FrameworkCache::cases() as $cache) {
                if (isset($process->environment[$cache->pathVariable()])) {
                    File::put($process->environment[$cache->pathVariable()], '<?php return ["fresh" => true];');
                }
            }

            return Process::result();
        });
    }

    protected function tearDown(): void
    {
        foreach (FrameworkCache::cases() as $cache) {
            unset($_SERVER[$cache->pathVariable()]);
        }

        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    public function test_it_succeeds_without_touching_caches_under_unit_tests(): void
    {
        $this->artisanCommand('module:refresh-caches')->assertExitCode(0);

        Process::assertNothingRan();
    }

    public function test_caches_in_use_are_rebuilt_in_place_config_first(): void
    {
        $this->expectArtisanCalls(['view:recompile', ...self::CLEARED]);

        $ran = $this->command()->refresh(FrameworkCache::cases());

        $this->assertSame(['config:cache', 'route:cache', 'event:cache', 'view:recompile', ...self::CLEARED], $ran);
        Process::assertRanInOrder(array_map(
            fn (string $command): \Closure => fn (PendingProcess $process): bool => is_array($process->command) && ($process->command[2] ?? null) === $command,
            ['config:cache', 'route:cache', 'event:cache'],
        ));

        foreach (FrameworkCache::cases() as $cache) {
            $this->assertSame('<?php return ["fresh" => true];', File::get($this->dir.'/'.$cache->value.'.php'));
        }
    }

    public function test_a_cache_in_use_is_never_dropped_before_its_replacement_is_ready(): void
    {
        $artisan = Artisan::spy();

        $ran = $this->command()->refresh(FrameworkCache::cases());

        foreach (['config:clear', 'route:clear', 'event:clear', 'view:clear', 'clear-compiled'] as $gap) {
            $this->assertNotContains($gap, $ran);
        }

        $artisan->shouldNotHaveReceived('call', ['config:clear']);
        $artisan->shouldNotHaveReceived('call', ['route:clear']);
        $artisan->shouldNotHaveReceived('call', ['event:clear']);
        $artisan->shouldNotHaveReceived('call', ['view:clear']);
        $artisan->shouldNotHaveReceived('call', ['clear-compiled']);
    }

    public function test_only_the_caches_in_use_are_rebuilt(): void
    {
        $this->expectArtisanCalls(['view:recompile', ...self::CLEARED]);

        $ran = $this->command()->refresh([FrameworkCache::Config]);

        // Routes not cached stay that way: a module change never starts caching them
        $this->assertSame(['config:cache', 'view:recompile', ...self::CLEARED], $ran);
        Process::assertRanTimes(fn (): bool => true, 1);
    }

    public function test_without_a_config_cache_compiled_views_are_cleared_and_nothing_is_rebuilt(): void
    {
        $this->expectArtisanCalls(['view:clear', ...self::CLEARED]);

        $ran = $this->command()->refresh([]);

        $this->assertSame(['view:clear', ...self::CLEARED], $ran);
        Process::assertNothingRan();
    }

    public function test_refresh_never_flushes_the_data_cache(): void
    {
        Artisan::shouldReceive('call')->andReturn(0);

        $ran = $this->command()->refresh(FrameworkCache::cases());

        $this->assertNotContains('cache:clear', $ran);
        $this->assertNotContains('optimize:clear', $ran);
    }

    /**
     * @param  list<string>  $commands
     */
    private function expectArtisanCalls(array $commands): void
    {
        foreach ($commands as $command) {
            Artisan::shouldReceive('call')->once()->ordered()->with($command)->andReturn(0);
        }
    }

    private function command(): RefreshCachesCommand
    {
        return $this->app->make(RefreshCachesCommand::class);
    }
}
