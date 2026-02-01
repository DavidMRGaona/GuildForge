<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Modules\ValueObjects\ModuleVersion;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class CoreVersionCommandTest extends TestCase
{
    private MockInterface&CoreVersionServiceInterface $versionService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->versionService = Mockery::mock(CoreVersionServiceInterface::class);
        $this->app->instance(CoreVersionServiceInterface::class, $this->versionService);
    }

    public function test_it_displays_current_version(): void
    {
        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn(ModuleVersion::fromString('1.5.3'));

        $this->versionService->shouldReceive('getCurrentCommit')
            ->andReturn('abc123def456789');

        $this->artisan('core:version')
            ->expectsOutput('GuildForge Core v1.5.3')
            ->assertExitCode(0);
    }

    public function test_it_displays_git_commit(): void
    {
        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn(ModuleVersion::fromString('2.0.0'));

        $this->versionService->shouldReceive('getCurrentCommit')
            ->andReturn('1234567890abcdef1234567890abcdef12345678');

        $this->artisan('core:version')
            ->expectsOutput('Git commit: 1234567890abcdef1234567890abcdef12345678')
            ->assertExitCode(0);
    }

    public function test_it_returns_success_exit_code(): void
    {
        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn(ModuleVersion::fromString('1.0.0'));

        $this->versionService->shouldReceive('getCurrentCommit')
            ->andReturn('unknown');

        $this->artisan('core:version')
            ->assertExitCode(0);
    }

    public function test_it_handles_unknown_git_commit(): void
    {
        $this->versionService->shouldReceive('getCurrentVersion')
            ->andReturn(ModuleVersion::fromString('1.0.0'));

        $this->versionService->shouldReceive('getCurrentCommit')
            ->andReturn('unknown');

        $this->artisan('core:version')
            ->expectsOutput('Git commit: unknown')
            ->assertExitCode(0);
    }
}
