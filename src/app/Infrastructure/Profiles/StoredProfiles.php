<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles;

use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;

/**
 * The profile rows read during one request, by account id, so the renderer (layout, banner) and the
 * core's sections (the bio) read each one once, and a page's authors are read together (findMany).
 * Bound scoped: a later request or job reads again.
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

    /**
     * The rows of several accounts at once (a page's authors, core#776): one query for those not read
     * yet in this request, none for the rest.
     *
     * @param  list<string>  $userIds  account ids in lower case, as they are stored
     * @return array<string, UserProfileModel> account id => row, in the order of the ids; accounts without one are left out
     */
    public function findMany(array $userIds): array
    {
        $missing = array_values(array_unique(array_filter(
            $userIds,
            fn (string $userId): bool => ! array_key_exists($userId, $this->profiles),
        )));

        if ($missing !== []) {
            $read = UserProfileModel::query()->whereKey($missing)->get()->keyBy(
                static fn (UserProfileModel $row): string => strtolower((string) $row->user_id),
            );

            foreach ($missing as $userId) {
                $this->profiles[$userId] = $read->get($userId);
            }
        }

        $found = [];
        foreach ($userIds as $userId) {
            $row = $this->profiles[$userId] ?? null;

            if ($row instanceof UserProfileModel) {
                $found[$userId] = $row;
            }
        }

        return $found;
    }
}
