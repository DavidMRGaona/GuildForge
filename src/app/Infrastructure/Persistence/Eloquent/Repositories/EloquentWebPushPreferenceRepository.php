<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Eloquent\Repositories;

use App\Domain\ValueObjects\UserId;
use App\Domain\WebPush\Repositories\WebPushPreferenceRepositoryInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserWebPushPreferenceModel;
use Illuminate\Support\Facades\DB;

final class EloquentWebPushPreferenceRepository implements WebPushPreferenceRepositoryInterface
{
    public function enabledFor(UserId $userId): array
    {
        /** @var list<string> */
        return UserWebPushPreferenceModel::query()
            ->where('user_id', $userId->value())
            ->orderBy('category')
            ->pluck('category')
            ->all();
    }

    public function save(UserId $userId, array $preferences): void
    {
        DB::transaction(function () use ($userId, $preferences): void {
            foreach ($preferences as $category => $enabled) {
                if ($enabled) {
                    UserWebPushPreferenceModel::query()->firstOrCreate(['user_id' => $userId->value(), 'category' => $category]);
                } else {
                    UserWebPushPreferenceModel::query()->where('user_id', $userId->value())->where('category', $category)->delete();
                }
            }
        });
    }

    public function deleteForUser(UserId $userId): void
    {
        UserWebPushPreferenceModel::query()->where('user_id', $userId->value())->delete();
    }
}
