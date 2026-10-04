<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Updates\Jobs;

use App\Application\Updates\DTOs\UpdateCheckResultDTO;
use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use App\Infrastructure\Updates\Jobs\CheckModuleUpdatesJob;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\TestCase;

final class CheckModuleUpdatesJobTest extends TestCase
{
    public function test_it_logs_repositories_that_could_not_be_checked(): void
    {
        Log::spy();
        $checker = Mockery::mock(ModuleUpdateCheckerInterface::class);
        $checker->shouldReceive('checkAll')->once()->andReturn(new UpdateCheckResultDTO(
            new Collection,
            ['game-tables' => "GitHub request for 'o/game-tables' failed: HTTP 403"],
            [],
        ));

        (new CheckModuleUpdatesJob)->handle($checker, $this->app->make(Dispatcher::class));

        Log::shouldHaveReceived('warning')->withArgs(
            fn (string $message, array $context = []): bool => str_contains($message, 'game-tables')
                && str_contains((string) ($context['error'] ?? ''), 'HTTP 403')
        );
        Log::shouldNotHaveReceived('info', ['Module update check completed: No updates available']);
    }
}
