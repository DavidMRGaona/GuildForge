<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Application\Authorization\Services\AssociationMembersAmongInterface;
use App\Application\Profiles\Services\ProfileMembershipDisplayInterface;
use App\Domain\Profiles\Exceptions\ProfileMembershipDisplayUnavailableException;
use App\Domain\Profiles\ProfileVisibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Throwable;

/**
 * The badge's conditions (core#824) asked about many accounts at once: the profile rows with their accounts
 * in one query, then who among the accounts that pass is an association member now
 * (AssociationMembersAmongInterface, the batched form of the rule the page's badge asks).
 *
 * Whatever fails to read throws ProfileMembershipDisplayUnavailableException, carrying nothing of its cause:
 * an answer of «false» for a failure would be taken for «does not show it» by whoever depends on it.
 */
final readonly class ProfileMembershipDisplay implements ProfileMembershipDisplayInterface
{
    /** The visibilities any signed-in member may see */
    private const array VISIBLE_TO_MEMBERS = [ProfileVisibility::Public, ProfileVisibility::Members];

    public function __construct(
        private AssociationMembersAmongInterface $members,
    ) {}

    public function showsMembership(array $userIds): array
    {
        $ids = [];
        foreach ($userIds as $userId) {
            if (Str::isUuid($userId)) {
                $ids[strtolower($userId)] = true;
            }
        }

        $ids = array_keys($ids);

        if (count($ids) > self::MAX_IDS) {
            throw new InvalidArgumentException(sprintf('At most %d accounts can be asked about at once.', self::MAX_IDS));
        }

        $answer = array_fill_keys($ids, false);

        try {
            foreach ($this->shown($ids) as $userId) {
                $answer[$userId] = true;
            }
        } catch (Throwable) {
            // No cause and no previous: a message may carry what a person can see
            throw ProfileMembershipDisplayUnavailableException::unreadable();
        }

        return $answer;
    }

    /**
     * @param  list<string>  $ids  account ids in lower case, as they are stored
     * @return list<string>
     */
    private function shown(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        // A raw join: deactivated accounts are rows of `users` too, and are told apart here
        $candidates = [];
        $rows = DB::table('user_profiles')
            ->join('users', 'users.id', '=', 'user_profiles.user_id')
            ->whereIn('user_profiles.user_id', $ids)
            ->where('user_profiles.show_membership', true)
            ->whereNull('users.deleted_at')
            ->whereNull('users.anonymized_at')
            ->get(['user_profiles.user_id', 'user_profiles.visibility', 'users.display_name']);

        foreach ($rows as $row) {
            $name = $row->display_name;

            if (is_string($name) && trim($name) !== ''
                && in_array(ProfileVisibility::fromStored($row->visibility), self::VISIBLE_TO_MEMBERS, true)) {
                $candidates[] = strtolower((string) $row->user_id);
            }
        }

        return array_keys($this->members->among($candidates));
    }
}
