<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles;

use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;

/**
 * The profile rows read during one request, by account id, so the renderer (layout, banner) and the
 * core's sections (the bio) read each one once. Bound scoped: a later request or job reads again.
 */
final class StoredProfiles
{
    /** @var array<string, UserProfileModel|null> */
    private array $profiles = [];

    public function find(string $userId): ?UserProfileModel
    {
        if (! array_key_exists($userId, $this->profiles)) {
            $this->profiles[$userId] = UserProfileModel::query()->find($userId);
        }

        return $this->profiles[$userId];
    }
}
