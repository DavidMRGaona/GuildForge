<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Modules\Listeners;

use App\Domain\Modules\Events\ModuleDisabled;
use App\Domain\Modules\Events\ModuleEnabled;
use App\Infrastructure\Modules\Listeners\ClearCachesOnModuleChange;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ClearCachesOnModuleChangeTest extends TestCase
{
    #[Test]
    public function it_clears_route_and_view_caches_on_module_enabled(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('route:clear')
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('view:clear')
            ->andReturn(0);

        $listener = new ClearCachesOnModuleChange();
        $event = new ModuleEnabled(
            moduleId: 'test-module-id',
            moduleName: 'announcements'
        );

        $listener->handle($event);
    }

    #[Test]
    public function it_clears_route_and_view_caches_on_module_disabled(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('route:clear')
            ->andReturn(0);

        Artisan::shouldReceive('call')
            ->once()
            ->with('view:clear')
            ->andReturn(0);

        $listener = new ClearCachesOnModuleChange();
        $event = new ModuleDisabled(
            moduleId: 'test-module-id',
            moduleName: 'announcements'
        );

        $listener->handle($event);
    }

    #[Test]
    public function it_logs_warning_when_cache_clearing_fails(): void
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('route:clear')
            ->andThrow(new \RuntimeException('Cache directory not writable'));

        Log::shouldReceive('warning')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return str_contains($message, 'Failed to clear Laravel caches')
                    && $context['module'] === 'announcements';
            });

        $listener = new ClearCachesOnModuleChange();
        $event = new ModuleEnabled(
            moduleId: 'test-module-id',
            moduleName: 'announcements'
        );

        $listener->handle($event);
    }
}
