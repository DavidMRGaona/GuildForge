<?php

declare(strict_types=1);

namespace App\Console\Commands\Media;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use Illuminate\Console\Command;

/**
 * Releases the usage rows of a type no active source owns, such as a module uninstalled together with its
 * data: until then the media library treats its files as used and never lets them be deleted.
 */
final class MediaForgetSourceCommand extends Command
{
    protected $signature = 'media:forget-source
        {type : Media source type whose usage rows are deleted (e.g. gametables.tables)}
        {--force : Delete without asking}';

    protected $description = 'Delete the media usage rows of a source type that is no longer active (an uninstalled module)';

    public function handle(MediaSourceRegistryInterface $registry): int
    {
        $type = (string) $this->argument('type');

        // An active source rewrites its rows on every save and sync: forgetting them would only hide uses for a while
        if ($registry->source($type) instanceof MediaUsageSourceInterface) {
            $this->error(sprintf('"%s" belongs to an active media source: its usages are kept up to date by media:sync.', $type));

            return self::FAILURE;
        }

        $count = MediaUsageModel::query()->where('source_type', $type)->count();

        if ($count === 0) {
            $this->info(sprintf('No usage rows of type "%s".', $type));

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm(sprintf('Delete %d usage rows of type "%s"? Their files will count as unused.', $count, $type))) {
            $this->info('Nothing deleted.');

            return self::SUCCESS;
        }

        $deleted = MediaUsageModel::query()->where('source_type', $type)->delete();

        $this->info(sprintf('Deleted %d usage rows of type "%s".', $deleted, $type));

        return self::SUCCESS;
    }
}
