<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Profiles\Services\ProfileMembershipDisplayInterface;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Authorization\Services\TwoFactorHolders;
use App\Infrastructure\Profiles\ProfileContributionRegistry;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * The badge's conditions (core#824) asked about many accounts at once: the profile rows with their accounts
 * in one query, the holders of the association member role among the accounts that pass in another, and
 * nothing else (two-factor only if that role itself requires it).
 *
 * The role is the one AssociationMembershipServiceInterface names, but its holders are read here, from the
 * stored roles: the interface answers one account at a time from a per-account cache. The association role
 * cannot require membership, and one that requires two-factor (written outside the role editor) counts only
 * for accounts that have it, as ActiveRoleResolver decides.
 *
 * The container is asked for scoped pieces on each call: a module may keep this service longer than a
 * request or a job.
 */
final readonly class ProfileMembershipDisplay implements ProfileMembershipDisplayInterface
{
    private DailyWarnings $warnings;

    /** The visibilities any signed-in member may see */
    private const array VISIBLE_TO_MEMBERS = [ProfileVisibility::Public, ProfileVisibility::Members];

    public function __construct(
        private AssociationMembershipServiceInterface $membership,
        private Container $container,
    ) {
        $this->warnings = new DailyWarnings(
            ProfileContributionRegistry::WARNING_PREFIX,
            static fn (): Cache => $container->make(Cache::class),
        );
    }

    public function showsMembership(array $userIds): array
    {
        $ids = [];
        foreach ($userIds as $userId) {
            if (Str::isUuid($userId)) {
                $ids[strtolower($userId)] = true;
            }
        }

        $ids = array_keys($ids);
        $answer = array_fill_keys($ids, false);

        if (count($ids) > self::MAX_IDS) {
            $this->warnings->warn('display|limit', 'The membership display of too many accounts was asked at once; the rest answer false.', [
                'reason' => 'too_many',
                'limit' => self::MAX_IDS,
            ]);
            $ids = array_slice($ids, 0, self::MAX_IDS);
        }

        try {
            foreach ($this->shown($ids) as $userId) {
                $answer[$userId] = true;
            }
        } catch (Throwable $exception) {
            // Only the class: a message may carry what a person can see
            $this->warnings->warn('display|read|'.$exception::class, 'The membership display of some accounts could not be read; it is treated as not shown.', [
                'reason' => 'unreadable',
                'exception' => $exception::class,
            ]);
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

        $roleName = $this->membership->roleName();

        if ($roleName === null) {
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

        if ($candidates === []) {
            return [];
        }

        $holders = DB::table('user_role')
            ->join('roles', 'roles.id', '=', 'user_role.role_id')
            ->where('roles.name', $roleName)
            ->whereIn('user_role.user_id', $candidates)
            ->get(['user_role.user_id', 'roles.requires_two_factor']);

        $members = [];
        $needsTwoFactor = false;

        foreach ($holders as $holder) {
            $members[strtolower((string) $holder->user_id)] = (bool) $holder->requires_two_factor;
            $needsTwoFactor = $needsTwoFactor || (bool) $holder->requires_two_factor;
        }

        $withTwoFactor = $needsTwoFactor
            ? $this->container->make(TwoFactorHolders::class)->among(array_keys($members))
            : [];

        $shown = [];
        foreach ($members as $userId => $requiresTwoFactor) {
            if (! $requiresTwoFactor || isset($withTwoFactor[$userId])) {
                $shown[] = $userId;
            }
        }

        return $shown;
    }
}
