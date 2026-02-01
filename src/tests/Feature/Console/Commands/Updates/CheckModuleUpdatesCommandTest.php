<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands\Updates;

use App\Application\Updates\DTOs\AvailableUpdateDTO;
use App\Application\Updates\Services\ModuleUpdateCheckerInterface;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

final class CheckModuleUpdatesCommandTest extends TestCase
{
    private MockInterface&ModuleUpdateCheckerInterface $updateChecker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->updateChecker = Mockery::mock(ModuleUpdateCheckerInterface::class);
        $this->app->instance(ModuleUpdateCheckerInterface::class, $this->updateChecker);
    }

    public function test_it_displays_available_updates_table(): void
    {
        $updates = new Collection([
            new AvailableUpdateDTO(
                moduleName: 'forum',
                displayName: 'Forum Module',
                currentVersion: '1.0.0',
                availableVersion: '1.2.0',
                releaseNotes: 'Bug fixes',
                publishedAt: new DateTimeImmutable('2024-08-15'),
                isPrerelease: false,
                isMajorUpdate: false,
                downloadUrl: 'https://example.com/forum.zip',
                hasChecksum: true,
            ),
            new AvailableUpdateDTO(
                moduleName: 'shop',
                displayName: 'Shop Module',
                currentVersion: '2.0.0',
                availableVersion: '3.0.0',
                releaseNotes: 'Major update',
                publishedAt: new DateTimeImmutable('2024-08-16'),
                isPrerelease: false,
                isMajorUpdate: true,
                downloadUrl: 'https://example.com/shop.zip',
                hasChecksum: true,
            ),
        ]);

        $this->updateChecker->shouldReceive('checkAllForUpdates')
            ->andReturn($updates);

        $this->artisan('module:check-updates')
            ->expectsOutput('Found 2 update(s) available:')
            ->expectsTable(
                ['Module', 'Current', 'Available', 'Major', 'Published'],
                [
                    ['forum', '1.0.0', '1.2.0', 'No', '2024-08-15'],
                    ['shop', '2.0.0', '3.0.0', 'Yes', '2024-08-16'],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_it_shows_message_when_all_up_to_date(): void
    {
        $this->updateChecker->shouldReceive('checkAllForUpdates')
            ->andReturn(new Collection());

        $this->artisan('module:check-updates')
            ->expectsOutput('All modules are up to date.')
            ->assertExitCode(0);
    }

    public function test_it_displays_single_update(): void
    {
        $updates = new Collection([
            new AvailableUpdateDTO(
                moduleName: 'gallery',
                displayName: 'Gallery Module',
                currentVersion: '1.5.0',
                availableVersion: '1.6.0',
                releaseNotes: 'Performance improvements',
                publishedAt: new DateTimeImmutable('2024-07-20'),
                isPrerelease: false,
                isMajorUpdate: false,
                downloadUrl: 'https://example.com/gallery.zip',
                hasChecksum: false,
            ),
        ]);

        $this->updateChecker->shouldReceive('checkAllForUpdates')
            ->andReturn($updates);

        $this->artisan('module:check-updates')
            ->expectsOutput('Found 1 update(s) available:')
            ->expectsTable(
                ['Module', 'Current', 'Available', 'Major', 'Published'],
                [
                    ['gallery', '1.5.0', '1.6.0', 'No', '2024-07-20'],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_it_displays_major_update_indicator(): void
    {
        $updates = new Collection([
            new AvailableUpdateDTO(
                moduleName: 'payments',
                displayName: 'Payments Module',
                currentVersion: '1.9.9',
                availableVersion: '2.0.0',
                releaseNotes: 'Breaking changes',
                publishedAt: new DateTimeImmutable('2024-09-01'),
                isPrerelease: false,
                isMajorUpdate: true,
                downloadUrl: 'https://example.com/payments.zip',
                hasChecksum: true,
            ),
        ]);

        $this->updateChecker->shouldReceive('checkAllForUpdates')
            ->andReturn($updates);

        $this->artisan('module:check-updates')
            ->expectsTable(
                ['Module', 'Current', 'Available', 'Major', 'Published'],
                [
                    ['payments', '1.9.9', '2.0.0', 'Yes', '2024-09-01'],
                ]
            )
            ->assertExitCode(0);
    }

    public function test_it_returns_success_exit_code(): void
    {
        $this->updateChecker->shouldReceive('checkAllForUpdates')
            ->andReturn(new Collection());

        $this->artisan('module:check-updates')
            ->assertExitCode(0);
    }
}
