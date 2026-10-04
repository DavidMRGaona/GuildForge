<?php

declare(strict_types=1);

namespace App\Console\Commands\Updates;

use App\Application\Updates\Services\CoreVersionServiceInterface;
use App\Domain\Updates\Enums\UpdateStatus;
use App\Infrastructure\Updates\Persistence\Eloquent\Models\CoreUpdateHistoryModel;
use Illuminate\Console\Command;

/**
 * Deployment log, written by the container entrypoint: every deployment boots a new
 * container, so its boot is where a core update happens.
 *
 *  - start: before migrating; opens a row from the last deployed commit to this one
 *  - finish: once the container is ready
 *  - fail: when a boot step breaks (the error is kept)
 *
 * Restarting a container on the same commit records nothing new, and booting again
 * after a failure reuses that deployment's row.
 */
final class RecordDeploymentCommand extends Command
{
    protected $signature = 'core:record-deployment
        {step : start, finish or fail}
        {--error= : What failed (for the fail step)}';

    protected $description = 'Record the deployment of the current commit (used by the container entrypoint)';

    public function handle(CoreVersionServiceInterface $versionService): int
    {
        $step = (string) $this->argument('step');

        if (! in_array($step, ['start', 'finish', 'fail'], true)) {
            $this->error("Unknown step '{$step}': use start, finish or fail.");

            return self::FAILURE;
        }

        $commit = $versionService->getCurrentCommit();

        if ($commit === 'unknown') {
            $this->warn('Deployed commit unknown (SOURCE_COMMIT is not set): deployment not recorded.');

            return self::SUCCESS;
        }

        $latest = CoreUpdateHistoryModel::query()->latest('created_at')->first();
        $current = $latest !== null && $latest->git_commit_after === $commit ? $latest : null;

        match ($step) {
            'start' => $this->start($current, $latest, $commit, $versionService->getCurrentVersion()->value()),
            'finish' => $current?->update(['status' => UpdateStatus::Completed, 'error_message' => null]),
            'fail' => $current?->update(['status' => UpdateStatus::Failed, 'error_message' => (string) ($this->option('error') ?? 'Deployment failed')]),
        };

        return self::SUCCESS;
    }

    private function start(?CoreUpdateHistoryModel $current, ?CoreUpdateHistoryModel $latest, string $commit, string $version): void
    {
        if ($current?->status === UpdateStatus::Completed) {
            return; // A restart of a deployment that already finished
        }

        if ($current !== null) {
            $current->update(['status' => UpdateStatus::Applying, 'error_message' => null]);

            return;
        }

        CoreUpdateHistoryModel::query()->create([
            'from_version' => $latest->to_version ?? $version,
            'to_version' => $version,
            'git_commit_before' => $latest->git_commit_after ?? '',
            'git_commit_after' => $commit,
            'status' => UpdateStatus::Applying,
        ]);
    }
}
