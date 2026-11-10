<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\SiteNotifications\Repositories\SiteNotificationPreferenceRepositoryInterface;
use App\Domain\ValueObjects\UserId;
use App\Infrastructure\Persistence\Eloquent\Models\UserSiteNotificationPreferenceModel;
use Illuminate\Support\Facades\DB;

final class EloquentSiteNotificationPreferenceRepository implements SiteNotificationPreferenceRepositoryInterface
{
    public function disabledFor(UserId $userId): array
    {
        /** @var list<string> */
        return UserSiteNotificationPreferenceModel::query()
            ->where('user_id', $userId->value())
            ->where('enabled', false)
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    public function save(UserId $userId, array $preferences): void
    {
        DB::transaction(function () use ($userId, $preferences): void {
            foreach ($preferences as $category => $enabled) {
                UserSiteNotificationPreferenceModel::query()->updateOrCreate(
                    ['user_id' => $userId->value(), 'category' => $category],
                    ['enabled' => $enabled],
                );
            }
        });
    }

    public function deleteForUser(UserId $userId): void
    {
        UserSiteNotificationPreferenceModel::query()->where('user_id', $userId->value())->delete();
    }
}
