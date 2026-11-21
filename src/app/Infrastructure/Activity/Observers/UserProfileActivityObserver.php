<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity\Observers;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Infrastructure\Activity\ProfileCompletion;
use App\Infrastructure\Activity\Sources\ProfileCompletedActivitySource;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A public profile whose «Sobre mí» changes, or that is deleted (core#777), may start or stop being
 * complete. Never fails the save.
 */
final readonly class UserProfileActivityObserver
{
    /** The container: an observer lives as long as the process, the publisher is scoped */
    public function __construct(
        private Container $container,
    ) {}

    public function created(UserProfileModel $profile): void
    {
        // Almost always without a «Sobre mí», but a seeder or an import may give one
        if (filled($profile->bio)) {
            $this->refresh($profile);
        }
    }

    public function updated(UserProfileModel $profile): void
    {
        if ($profile->wasChanged('bio')) {
            $this->refresh($profile);
        }
    }

    private function refresh(UserProfileModel $profile): void
    {
        $this->safely($profile, fn () => $this->container->make(ProfileCompletion::class)->refresh((string) $profile->user_id));
    }

    public function deleted(UserProfileModel $profile): void
    {
        $this->safely($profile, fn () => $this->container->make(ActivityPublisherInterface::class)->recorded(ProfileCompletedActivitySource::TYPE, strtolower((string) $profile->user_id)));
    }

    private function safely(UserProfileModel $profile, callable $publish): void
    {
        try {
            $publish();
        } catch (Throwable $exception) {
            Log::warning('The profile activity of an account could not be published.', [
                'user_id' => (string) $profile->user_id,
                'exception' => $exception::class,
            ]);
        }
    }
}
