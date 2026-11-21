<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Domain\Profiles\ProfileCompletenessRule;
use App\Infrastructure\Activity\Sources\ProfileCompletedActivitySource;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;

/**
 * Keeps `user_profiles.completed_at` in step with the profile (core#777) and tells the activity
 * contract that the account's profile may have changed. The date is set the first time public name,
 * avatar and «Sobre mí» are all there and cleared when one goes, so it never moves for an unrelated
 * change. It is written with the query builder: no model event, no touching of `updated_at`.
 */
final readonly class ProfileCompletion
{
    public function __construct(
        private ActivityPublisherInterface $activity,
    ) {}

    public function refresh(string $userId): void
    {
        $user = UserModel::withTrashed()->find($userId);
        $profile = UserProfileModel::query()->find($userId);

        if ($profile instanceof UserProfileModel) {
            $complete = $user instanceof UserModel
                && $user->anonymized_at === null
                && ProfileCompletenessRule::isComplete($user->display_name, $user->avatar_public_id, $profile->bio);

            if ($complete && $profile->completed_at === null) {
                UserProfileModel::query()->whereKey($userId)->toBase()->update(['completed_at' => now()]);
            } elseif (! $complete && $profile->completed_at !== null) {
                UserProfileModel::query()->whereKey($userId)->toBase()->update(['completed_at' => null]);
            }
        }

        $this->activity->recorded(ProfileCompletedActivitySource::TYPE, strtolower($userId));
    }
}
