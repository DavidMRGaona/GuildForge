<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use App\Application\Updates\DTOs\CoreUpdateStatusDTO;
use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use App\Domain\Updates\Exceptions\UpdateException;
use Mockery;
use Tests\TestCase;

final class CoreCheckUpdatesCommandTest extends TestCase
{
    private const string DEPLOYED = 'abc1234def4567890abc123def4567890abc1234';

    public function test_it_lists_the_commits_waiting_to_be_deployed(): void
    {
        $this->checkReturns(new CoreUpdateStatusDTO(self::DEPLOYED, 'main', 1, 'bbb', [
            ['sha' => 'bbbbbbbbbb', 'message' => 'fix: one', 'date' => '2026-10-04T10:00:00Z', 'url' => 'https://github.com/o/r/commit/bbb'],
        ], false));

        $this->artisan('core:check-updates')
            ->expectsOutputToContain('abc1234')
            ->expectsOutputToContain('1 commit(s) on main are not deployed')
            ->expectsOutputToContain('fix: one')
            ->assertExitCode(0);
    }

    public function test_it_reports_an_up_to_date_deployment(): void
    {
        $this->checkReturns(new CoreUpdateStatusDTO(self::DEPLOYED, 'main', 0, self::DEPLOYED, [], false));

        $this->artisan('core:check-updates')
            ->expectsOutputToContain('The deployed commit is the latest on main.')
            ->assertExitCode(0);
    }

    public function test_it_fails_when_the_check_fails(): void
    {
        $checker = Mockery::mock(CoreUpdateCheckerInterface::class);
        $checker->shouldReceive('check')->andThrow(UpdateException::githubRequestFailed('o/r', 'HTTP 403'));
        $this->app->instance(CoreUpdateCheckerInterface::class, $checker);

        $this->artisan('core:check-updates')
            ->expectsOutputToContain('HTTP 403')
            ->assertExitCode(1);
    }

    private function checkReturns(CoreUpdateStatusDTO $status): void
    {
        $checker = Mockery::mock(CoreUpdateCheckerInterface::class);
        $checker->shouldReceive('check')->andReturn($status);
        $this->app->instance(CoreUpdateCheckerInterface::class, $checker);
    }
}
