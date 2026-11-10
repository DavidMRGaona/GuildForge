<?php

declare(strict_types=1);

namespace App\Console\Commands\Media;

use App\Application\Media\Services\MediaIndexerInterface;
use Illuminate\Console\Command;

final class MediaSyncCommand extends Command
{
    protected $signature = 'media:sync';

    protected $description = 'Reconcile the media library index with Cloudinary and with the usages of every active media source';

    public function handle(MediaIndexerInterface $indexer): int
    {
        $result = $indexer->sync();

        $this->info('Files: '.$result->files);
        $this->info('Added: '.$result->added);
        $this->info('Removed: '.$result->removed);

        $complete = true;

        if (! $result->catalogComplete) {
            $this->warn('The Cloudinary listing could not be read completely: no file was added or removed.');
            $complete = false;
        }

        if ($result->failedSources !== []) {
            $this->warn('Usages not refreshed for: '.implode(', ', $result->failedSources));
            $complete = false;
        }

        return $complete ? self::SUCCESS : self::FAILURE;
    }
}
