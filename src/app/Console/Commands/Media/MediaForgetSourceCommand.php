<?php

declare(strict_types=1);

namespace App\Console\Commands\Media;

use App\Application\Media\Contracts\MediaUsageSourceInterface;
use App\Application\Media\Services\MediaSourceRegistryInterface;
use App\Console\Concerns\ReadsTypedInput;
use App\Domain\Modules\Exceptions\InvalidModuleNameException;
use App\Domain\Modules\Repositories\ModuleRepositoryInterface;
use App\Domain\Modules\ValueObjects\ModuleName;
use App\Infrastructure\Media\Persistence\Eloquent\Models\MediaUsageModel;
use App\Infrastructure\Media\Services\MediaSourceSyncState;
use Illuminate\Console\Command;

/**
 * Releases the usage rows of a type no active source owns, such as a module uninstalled together with its
 * data: until then the media library treats its files as used and never lets them be deleted. A module that
 * is only disabled is still installed: its rows are kept, so enabling it again finds its images.
 */
final class MediaForgetSourceCommand extends Command
{
    use ReadsTypedInput;

    protected $signature = 'media:forget-source
        {type : Media source type whose usage rows are deleted (e.g. gametables.tables)}
        {--force : Delete without asking}';

    protected $description = 'Delete the media usage rows of a source type that is no longer active (an uninstalled module)';

    public function handle(MediaSourceRegistryInterface $registry, ModuleRepositoryInterface $modules, MediaSourceSyncState $syncState): int
    {
        $type = $this->stringArgument('type');

        // An active source rewrites its rows on every save and sync: forgetting them would only hide uses for a while
        if ($registry->source($type) instanceof MediaUsageSourceInterface) {
            $this->error(sprintf('"%s" belongs to an active media source: its usages are kept up to date by media:sync.', $type));

            return self::FAILURE;
        }

        $installed = $this->installedModuleOf($type, $modules);

        if ($installed !== null) {
            $this->error(sprintf('"%s" belongs to the module "%s", which is still installed: its usages are kept until it is uninstalled.', $type, $installed));

            return self::FAILURE;
        }

        $count = MediaUsageModel::query()->where('source_type', $type)->count();

        // Without rows nothing tells whose type it is: its failure is kept, so it fails closed. Its fingerprint
        // goes all the same: that only conceals its files until a sync reads the source again
        if ($count === 0) {
            if (! $syncState->forgetFingerprint($type)) {
                $this->error(sprintf('No usage rows of type "%s", but its sync state could not be updated. Run the command again.', $type));

                return self::FAILURE;
            }

            $this->info(sprintf('No usage rows of type "%s".', $type));

            return self::SUCCESS;
        }

        if (! $this->option('force')
            && ! $this->confirm(sprintf('Delete %d usage rows of type "%s"? Their files will count as unused.', $count, $type))) {
            $this->info('Nothing deleted.');

            return self::SUCCESS;
        }

        // First, so that only a complete sync that does not fail counts the type as up to date again: a module
        // installed again with the same version and its data kept would otherwise count with no rows (core#853)
        if (! $syncState->forgetFingerprint($type)) {
            $this->error(sprintf('The sync state of "%s" could not be updated; nothing was deleted. Run the command again.', $type));

            return self::FAILURE;
        }

        $deleted = MediaUsageModel::query()->where('source_type', $type)->delete();
        $this->info(sprintf('Deleted %d usage rows of type "%s".', $deleted, $type));

        // Nothing of the type is left to conceal; a failure left behind only keeps it concealed
        if (! $syncState->forgetRefreshFailure($type)) {
            $this->error(sprintf('The refresh failure of "%s" could not be cleared: the type stays concealed until a sync reads its source again.', $type));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * The first module of the type's rows that is still installed (enabled or not), or null when none is.
     */
    private function installedModuleOf(string $type, ModuleRepositoryInterface $modules): ?string
    {
        /** @var list<string> $names */
        $names = MediaUsageModel::query()
            ->where('source_type', $type)
            ->whereNotNull('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module')
            ->all();

        foreach ($names as $name) {
            try {
                $moduleName = new ModuleName($name);
            } catch (InvalidModuleNameException) {
                // No module can be installed under such a name
                continue;
            }

            if ($modules->exists($moduleName)) {
                return $name;
            }
        }

        return null;
    }
}
