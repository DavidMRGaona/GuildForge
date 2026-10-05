<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use App\Console\Commands\Updates\RefreshCachesCommand;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

final class RefreshCachesCommandTest extends TestCase
{
    private const array CLEAR = [
        'clear-compiled',
        'config:clear',
        'event:clear',
        'route:clear',
        'view:clear',
        'icons:clear',
        'filament:optimize-clear',
    ];

    public function test_it_succeeds_without_touching_caches_under_unit_tests(): void
    {
        $this->artisan('module:refresh-caches')->assertExitCode(0);
    }

    public function test_refresh_clears_framework_caches_and_rebuilds_the_cached_ones(): void
    {
        $expected = [...self::CLEAR, 'config:cache', 'view:cache', 'route:cache'];

        foreach ($expected as $command) {
            Artisan::shouldReceive('call')->once()->ordered()->with($command)->andReturn(0);
        }

        $ran = (new RefreshCachesCommand())->refresh(configCached: true, routesCached: true);

        $this->assertSame($expected, $ran);
    }

    public function test_refresh_only_clears_when_nothing_was_cached(): void
    {
        foreach (self::CLEAR as $command) {
            Artisan::shouldReceive('call')->once()->ordered()->with($command)->andReturn(0);
        }

        $ran = (new RefreshCachesCommand())->refresh(configCached: false, routesCached: false);

        $this->assertSame(self::CLEAR, $ran);
    }

    public function test_refresh_never_flushes_the_data_cache(): void
    {
        Artisan::shouldReceive('call')->andReturn(0);

        $ran = (new RefreshCachesCommand())->refresh(configCached: true, routesCached: true);

        $this->assertNotContains('cache:clear', $ran);
        $this->assertNotContains('optimize:clear', $ran);
    }
}
