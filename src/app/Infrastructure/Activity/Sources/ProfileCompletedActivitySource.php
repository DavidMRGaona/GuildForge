<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity\Sources;

use App\Domain\Activity\ActivityKind;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Complete public profile (core#777): an active account with a public name, an avatar and a «Sobre
 * mí» (ProfileCompletenessRule, written here as a query). The fact is the account as `owner`.
 *
 * `occurredAt` is when the profile became complete: `user_profiles.completed_at`, set by
 * ProfileCompletion the first time all three are there and cleared when one goes, so changing
 * anything else (visibility, banner, the text of «Sobre mí») never moves it. A profile that was
 * already complete when the column was added has its last change as the date. When the profile
 * stops being complete the account stops counting, and counts again, with the new date, when it is
 * completed again; what the consumer already granted is the consumer's business.
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

    protected function counted(): Builder
    {
        return UserModel::query()
            ->whereNull('anonymized_at')
            ->whereNotNull('display_name')
            ->whereRaw("trim(display_name) <> ''")
            ->whereNotNull('avatar_public_id')
            ->whereRaw("trim(avatar_public_id) <> ''")
            ->whereIn('users.id', UserProfileModel::query()
                ->select('user_id')
                ->whereNotNull('bio')
                ->whereRaw("trim(bio) <> ''"))
            ->orderBy('users.id');
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
            $instant = $profile instanceof UserProfileModel ? ($profile->completed_at ?? $profile->updated_at) : null;

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
