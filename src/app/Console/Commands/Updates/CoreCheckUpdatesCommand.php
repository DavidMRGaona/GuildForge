<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Application\Updates\Services\CoreUpdateCheckerInterface;
use App\Application\Updates\Services\CoreVersionServiceInterface;
use Illuminate\Console\Command;

final class CoreCheckUpdatesCommand extends Command
{
    protected $signature = 'core:check-updates';

    protected $description = 'Check for available core updates';

    public function handle(
        CoreVersionServiceInterface $versionService,
        CoreUpdateCheckerInterface $updateChecker,
    ): int {
        $currentVersion = $versionService->getCurrentVersion();

        $this->info("Current core version: v{$currentVersion->value()}");
        $this->info('Checking for updates...');

        $latestRelease = $updateChecker->checkForUpdate();

        if ($latestRelease === null) {
            $this->info('You are running the latest version.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Update available!');
        $this->table(
            ['Field', 'Value'],
            [
                ['Current version', "v{$currentVersion->value()}"],
                ['Available version', "v{$latestRelease->version->value()}"],
                ['Published', $latestRelease->publishedAt->format('Y-m-d H:i')],
                ['Pre-release', $latestRelease->isPrerelease ? 'Yes' : 'No'],
            ]
        );

        if ($latestRelease->releaseNotes !== '') {
            $this->newLine();
            $this->info('Release notes:');
            $this->line($latestRelease->releaseNotes);
        }

        $this->newLine();
        $this->warn('To update, run your deployment process with the new version.');

        return self::SUCCESS;
    }
}
