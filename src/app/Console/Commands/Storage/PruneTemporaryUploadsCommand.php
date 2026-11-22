<?php

declare(strict_types=1);

namespace App\Console\Commands\Storage;

use App\Infrastructure\Storage\TemporaryUploads\TemporaryUploadPruner;
use Illuminate\Console\Command;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;

/**
 * Deletes the Livewire temporary uploads (and their metadata) older than an hour (core#827), on the
 * disk and directory Livewire uploads to. Runs hourly.
 */
final class PruneTemporaryUploadsCommand extends Command
{
    protected $signature = 'uploads:prune-temporary';

    protected $description = 'Delete Livewire temporary uploads older than an hour';

    public function handle(TemporaryUploadPruner $pruner): int
    {
        $result = $pruner->prune(
            FileUploadConfiguration::disk(),
            FileUploadConfiguration::storage(),
            FileUploadConfiguration::directory(),
            now()->subMinutes(TemporaryUploadPruner::MAX_AGE_MINUTES)->getTimestamp(),
        );

        if (! $result->listed) {
            $this->error('Temporary uploads were not pruned; see the log.');

            return self::FAILURE;
        }

        $this->info("Deleted {$result->deleted} temporary uploads; {$result->failed} could not be deleted.");

        return $result->failed === 0 ? self::SUCCESS : self::FAILURE;
    }
}
