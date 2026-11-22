<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity\Observers;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Infrastructure\Activity\ProfileCompletion;
use App\Infrastructure\Activity\Sources\AccountActivitySource;
use App\Infrastructure\Activity\Sources\ProfileCompletedActivitySource;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * What a public profile's changes do to what the core reports (core#777): a «Sobre mí» that changes
 * may complete the profile or not, a visibility that changes decides whether the account and its
 * profile are something every member can know exists, and a deleted profile counts no more. Each
 * publication runs in a savepoint and never fails the save.
 */
final readonly class UserProfileActivityObserver
{
    /** Laravel builds an observer for every event it handles, so it gets the publisher of the request or job at hand */
    public function __construct(
        private ActivityPublisherInterface $activity,
        private ProfileCompletion $completion,
    ) {}

    public function created(UserProfileModel $profile): void
    {
        // Almost always without a «Sobre mí», but a seeder or an import may give one
        if (filled($profile->bio)) {
            $this->safely($profile, fn () => $this->completion->refresh((string) $profile->user_id));
        }
    }

    public function updated(UserProfileModel $profile): void
    {
        if ($profile->wasChanged('bio')) {
            $this->safely($profile, fn () => $this->completion->refresh((string) $profile->user_id));
        }

        if ($profile->wasChanged('visibility')) {
            $this->safely($profile, function () use ($profile): void {
                $userId = strtolower((string) $profile->user_id);

                $this->activity->recorded(AccountActivitySource::TYPE, $userId);
                $this->activity->recorded(ProfileCompletedActivitySource::TYPE, $userId);
            });
        }
    }

    public function deleted(UserProfileModel $profile): void
    {
        $this->safely($profile, fn () => $this->activity->recorded(ProfileCompletedActivitySource::TYPE, strtolower((string) $profile->user_id)));
    }

    private function safely(UserProfileModel $profile, Closure $publish): void
    {
        try {
            DB::transaction(static fn (): mixed => $publish());
        } catch (Throwable $exception) {
            Log::warning('The profile activity of an account could not be published.', [
                'user_id' => (string) $profile->user_id,
                'exception' => $exception::class,
            ]);
        }
    }
}
