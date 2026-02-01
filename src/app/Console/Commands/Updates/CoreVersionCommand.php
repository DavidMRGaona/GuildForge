<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Application\Updates\Services\CoreVersionServiceInterface;
use Illuminate\Console\Command;

final class CoreVersionCommand extends Command
{
    protected $signature = 'core:version';

    protected $description = 'Display the current core application version';

    public function handle(CoreVersionServiceInterface $versionService): int
    {
        $version = $versionService->getCurrentVersion();
        $commit = $versionService->getCurrentCommit();

        $this->info("GuildForge Core v{$version->value()}");
        $this->info("Git commit: {$commit}");

        return self::SUCCESS;
    }
}
