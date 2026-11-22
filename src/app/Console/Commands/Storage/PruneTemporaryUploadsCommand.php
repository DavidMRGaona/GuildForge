<?php

declare(strict_types=1);

namespace App\Console\Commands\Storage;

use App\Infrastructure\Storage\TemporaryUploads\TemporaryUploadPruner;
use Illuminate\Console\Command;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Deletes the Livewire temporary uploads (and their metadata) older than six hours (core#827), on
 * the disk and directory Livewire uploads to. Runs hourly.
 */
final class PruneTemporaryUploadsCommand extends Command
{
    protected $signature = 'uploads:prune-temporary';

    protected $description = 'Delete Livewire temporary uploads older than six hours';

    public function handle(TemporaryUploadPruner $pruner, LoggerInterface $logger): int
    {
        try {
            // A directory with `..` or a disk that is not configured throws here
            $diskName = FileUploadConfiguration::disk();
            $disk = FileUploadConfiguration::storage();
            $directory = FileUploadConfiguration::directory();
        } catch (Throwable $exception) {
            $logger->error('Temporary uploads not pruned: the upload configuration could not be read', [
                'exception' => $exception::class,
            ]);
            $this->error('Temporary uploads were not pruned; see the log.');

            return self::FAILURE;
        }

        $result = $pruner->prune(
            $diskName,
            $disk,
            $directory,
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
