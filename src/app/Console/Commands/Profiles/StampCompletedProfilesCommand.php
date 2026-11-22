<?php

declare(strict_types=1);

namespace App\Console\Commands\Profiles;

use App\Infrastructure\Activity\ProfileCompletion;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * Stamps `user_profiles.completed_at` on the profiles that were already complete (public name, avatar
 * and «Sobre mí») when the date was added (core#777), with their last change as the best date there
 * is, and tells the activity contract about each. A site runs it once after deploying; later the
 * date is kept by the profile's own changes. Idempotent: a profile with a date, or one that is not
 * complete, is left alone. Never runs on a request.
 */
final class StampCompletedProfilesCommand extends Command
{
    protected $signature = 'profiles:stamp-completed
                            {--dry-run : Count the profiles that would be stamped without changing anything}
                            {--chunk=500 : Profiles handled per batch}';

    protected $description = 'Stamp the date on public profiles that were already complete before profiles kept it';

    public function handle(ProfileCompletion $completion): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $chunk = max(1, (int) $this->option('chunk'));
        $stamped = 0;

        // Only a profile with a «Sobre mí» can be complete: the query only narrows, the rule decides
        UserProfileModel::query()
            ->whereNull('completed_at')
            ->whereNotNull('bio')
            ->chunkById($chunk, function (Collection $profiles) use ($completion, $dryRun, &$stamped): void {
                foreach ($profiles as $profile) {
                    if ($completion->stampExisting((string) $profile->user_id, $dryRun)) {
                        $stamped++;
                    }
                }
            }, 'user_id');

        $this->components->info(trans_choice(
            $dryRun ? 'profiles.stamp_completed.would_stamp' : 'profiles.stamp_completed.stamped',
            $stamped,
            ['count' => $stamped],
        ));

        if ($dryRun) {
            $this->components->info((string) __('profiles.stamp_completed.dry_run_note'));
        }

        return self::SUCCESS;
    }
}
