<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Profiles\ProfileCompletenessRule;
use App\Infrastructure\Activity\Sources\ProfileCompletedActivitySource;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Support\Facades\DB;

/**
 * Keeps `user_profiles.completed_at` in step with the profile (core#777) and tells the activity
 * contract when what it reports changed. The date is set the first time public name, avatar and
 * «Sobre mí» are all there and cleared when one goes, so it never moves for an unrelated change. It
 * is the only writer of the column, through ProfileCompletenessRule, and writes with the query
 * builder: no model event, no touching of `updated_at`.
 */
final readonly class ProfileCompletion
{
    public function __construct(
        private ActivityPublisherInterface $activity,
    ) {}

    /**
     * Stamps or clears the date as the profile is now, and publishes the profile when that changed
     * it, or always with $publish (an account that was anonymized, deactivated, restored or
     * deleted: what counts depends on more than the date).
     */
    public function refresh(string $userId, bool $publish = false): void
    {
        // Its own savepoint, so a failing query cannot abort the caller's transaction; the publication
        // stays outside it, in the caller's, so the publisher announces a subject once per transaction
        $changed = DB::transaction(function () use ($userId): bool {
            $profile = UserProfileModel::query()->find($userId);

            if (! $profile instanceof UserProfileModel) {
                return false;
            }

            $complete = $this->isComplete($userId, $profile);

            if ($complete && $profile->completed_at === null) {
                UserProfileModel::query()->whereKey($userId)->whereNull('completed_at')->toBase()->update(['completed_at' => now()]);

                return true;
            }

            if (! $complete && $profile->completed_at !== null) {
                UserProfileModel::query()->whereKey($userId)->whereNotNull('completed_at')->toBase()->update(['completed_at' => null]);

                return true;
            }

            return false;
        });

        if ($changed || $publish) {
            $this->activity->recorded(ProfileCompletedActivitySource::TYPE, strtolower($userId));
        }
    }

    /**
     * For `profiles:stamp-completed`: a profile that is complete and has no date (it was complete
     * before the column existed) gets its last change as the best date there is, and is published.
     * With $dryRun nothing is written. A date written meanwhile (by a change of the profile itself)
     * is never overwritten.
     *
     * @return bool whether the profile was (or, in a dry run, would be) stamped
     */
    public function stampExisting(string $userId, bool $dryRun = false): bool
    {
        $profile = UserProfileModel::query()->find($userId);

        if (! $profile instanceof UserProfileModel || $profile->completed_at !== null || ! $this->isComplete($userId, $profile)) {
            return false;
        }

        if ($dryRun) {
            return true;
        }

        $stamped = UserProfileModel::query()->whereKey($userId)->whereNull('completed_at')->toBase()->update(['completed_at' => $profile->updated_at ?? now()]);

        if ($stamped > 0) {
            $this->activity->recorded(ProfileCompletedActivitySource::TYPE, strtolower($userId));
        }

        return $stamped > 0;
    }

    private function isComplete(string $userId, UserProfileModel $profile): bool
    {
        $user = UserModel::withTrashed()->find($userId);

        return $user instanceof UserModel
            && $user->anonymized_at === null
            && ProfileCompletenessRule::isComplete($user->display_name, $user->avatar_public_id, $profile->bio);
    }
}
