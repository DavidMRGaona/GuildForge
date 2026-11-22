<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage\TemporaryUploads;

use Illuminate\Contracts\Filesystem\Filesystem;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Deletes the files Livewire keeps while an upload waits to be used (core#827): the upload and its
 * `.json` metadata, each by its own age, so metadata left alone goes too. Files uploaded through the
 * panel may hold personal data (member imports), and Livewire only prunes them inside a later upload,
 * on a local disk and after a day.
 *
 * Only the contract's methods are used, so a local and a remote disk behave alike. Nothing outside
 * the directory is touched: an empty directory (the whole disk) is refused and every listed path must
 * sit under it. Logs carry the disk, counts and exception classes, never a file name or content.
 */
final readonly class TemporaryUploadPruner
{
    public const int MAX_AGE_MINUTES = 60;

    public function __construct(private LoggerInterface $logger) {}

    /**
     * @param  int  $cutoff  Unix time; files last modified before it go
     */
    public function prune(string $diskName, Filesystem $disk, string $directory, int $cutoff): TemporaryUploadPruneResult
    {
        $directory = trim($directory, '/');

        if ($directory === '') {
            $this->logger->error('Temporary uploads not pruned: the directory names the whole disk', ['disk' => $diskName]);

            return TemporaryUploadPruneResult::notListed();
        }

        try {
            $paths = $disk->allFiles($directory);
        } catch (Throwable $exception) {
            $this->logger->error('Temporary uploads not pruned: the directory could not be listed', [
                'disk' => $diskName,
                'exception' => $exception::class,
            ]);

            return TemporaryUploadPruneResult::notListed();
        }

        $prefix = $directory.'/';
        $deleted = 0;
        $failures = [];

        foreach ($paths as $path) {
            if (! str_starts_with($path, $prefix)) {
                continue;
            }

            try {
                if (! $this->isOlderThan($disk, $path, $cutoff)) {
                    continue;
                }

                if ($disk->delete($path)) {
                    $deleted++;
                } else {
                    $failures[] = null;
                }
            } catch (Throwable $exception) {
                $failures[] = $exception::class;
            }
        }

        if ($failures !== []) {
            $this->logger->warning('Some temporary uploads could not be pruned', [
                'disk' => $diskName,
                'failed' => count($failures),
                'exceptions' => array_values(array_unique(array_filter($failures))),
            ]);
        }

        return new TemporaryUploadPruneResult(listed: true, deleted: $deleted, failed: count($failures));
    }

    private function isOlderThan(Filesystem $disk, string $path, int $cutoff): bool
    {
        try {
            return $disk->lastModified($path) < $cutoff;
        } catch (Throwable $exception) {
            // Gone since the listing (another run, or the code that used it): nothing left to do
            if (! $disk->exists($path)) {
                return false;
            }

            throw $exception;
        }
    }
}
