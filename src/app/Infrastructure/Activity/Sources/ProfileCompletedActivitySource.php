<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity\Sources;

use App\Domain\Activity\ActivityKind;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Complete public profile (core#777): an active account whose profile every member may see and that
 * has a public name, an avatar and a «Sobre mí» (ProfileCompletenessRule). The fact is the account
 * as `owner`.
 *
 * It counts by `user_profiles.completed_at`, a column written only through the PHP rule
 * (ProfileCompletion), so a query never disagrees with it. `occurredAt` is that date: set the first
 * time all three are there and cleared when one goes, so changing anything else (visibility, banner,
 * the text of «Sobre mí») never moves it. A profile that was already complete before the column
 * existed does not count until `profiles:stamp-completed` stamps it. When the profile stops being
 * complete the account stops counting, and counts again, with the new date, when it is completed
 * again; what the consumer already granted is the consumer's business.
 */
final class ProfileCompletedActivitySource extends AccountBasedActivitySource
{
    public const string TYPE = 'core.profile-completed';

    public function type(): string
    {
        return self::TYPE;
    }

    public function kind(): ActivityKind
    {
        return ActivityKind::ProfileCompleted;
    }

    public function label(): string
    {
        return __('activity.profile_completed.label');
    }

    protected function narrowProfiles(Builder $profiles): Builder
    {
        // Written only through ProfileCompletenessRule: SQL's trim() never decides what is complete
        return $profiles->whereNotNull('completed_at');
    }

    protected function occurredAt(Collection $users): array
    {
        $profiles = UserProfileModel::query()->whereKey($users->modelKeys())->get()->keyBy(
            static fn (UserProfileModel $profile): string => strtolower((string) $profile->user_id),
        );
        $instants = [];

        foreach ($users as $user) {
            $id = strtolower((string) $user->id);
            $profile = $profiles->get($id);
            $instant = $profile instanceof UserProfileModel ? $profile->completed_at : null;

            if ($instant !== null) {
                $instants[$id] = DateTimeImmutable::createFromInterface($instant);
            }
        }

        return $instants;
    }

    protected function titleOf(string $publicName): string
    {
        return __('activity.profile_completed.title', ['name' => $publicName]);
    }

    protected function anonymousTitle(): string
    {
        return __('activity.profile_completed.title_private');
    }
}
