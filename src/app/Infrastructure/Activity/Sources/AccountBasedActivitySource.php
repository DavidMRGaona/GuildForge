<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity\Sources;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivitySubjectDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Application\Profiles\DTOs\PublicProfileDTO;
use App\Application\Profiles\Services\ProfileAccessRule;
use App\Domain\Activity\ActivityRole;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\Services\PublicProfileService;
use App\Infrastructure\Profiles\StoredProfiles;
use DateTimeImmutable;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * What the core's account sources share (core#777): the subject is an account (its UUID), the one
 * fact is the account as `owner`, and an account counts only while it is active (neither
 * deactivated nor anonymized) with a public name and a profile every signed-in member may see
 * (public or members). The sources differ in what else counts and when it happened.
 *
 * A deactivated account's profile cannot be seen by anybody (ProfileAccessRule), so what a member
 * could know about it is nothing: it does not count until it is restored, and an anonymized one
 * never does. A profile only the association or its owner may see does not count either. The
 * answer never depends on who is signed in.
 */
abstract class AccountBasedActivitySource implements ActivitySourceInterface
{
    public function __construct(
        private readonly StoredProfiles $profiles,
        private readonly ProfileAccessRule $access,
    ) {}

    /**
     * What else a profile needs for its account to count, besides being seen by every member.
     *
     * @param  Builder<UserProfileModel>  $profiles
     * @return Builder<UserProfileModel>
     */
    protected function narrowProfiles(Builder $profiles): Builder
    {
        return $profiles;
    }

    /**
     * The accounts that count, ordered by id: active (soft deleted ones are left out by the model),
     * with a public name and a profile every signed-in member may see (core#777): what a member
     * could not know exists is never reported, since a badge built on a fact is shown to others.
     *
     * @return Builder<UserModel>
     */
    protected function counted(): Builder
    {
        $visibleProfiles = UserProfileModel::query()
            ->select('user_id')
            ->whereIn('visibility', array_map(
                static fn (ProfileVisibility $visibility): string => $visibility->value,
                array_filter(ProfileVisibility::cases(), static fn (ProfileVisibility $visibility): bool => $visibility->isSeenByEveryMember()),
            ));

        return UserModel::query()
            ->whereNull('anonymized_at')
            ->whereNotNull('display_name')
            ->whereRaw("trim(display_name) <> ''")
            ->whereIn('users.id', $this->narrowProfiles($visibleProfiles))
            ->orderBy('users.id');
    }

    /**
     * When each account's fact happened.
     *
     * @param  Collection<int, UserModel>  $users
     * @return array<string, DateTimeImmutable> account id (lower case) => instant
     */
    abstract protected function occurredAt(Collection $users): array;

    /** The title of a subject whose profile the viewer may see (it names the person) */
    abstract protected function titleOf(string $publicName): string;

    /** The title of any other: it names nobody */
    abstract protected function anonymousTitle(): string;

    public function facts(string $subjectId): array
    {
        $id = $this->accountId($subjectId);

        if ($id === null) {
            return [];
        }

        $users = $this->counted()->whereKey($id)->get();

        return array_values($this->factsOf($users));
    }

    public function history(?string $cursor, int $limit): ActivityHistoryPageDTO
    {
        $after = null;

        if ($cursor !== null) {
            $after = $this->accountId(OpaqueCursor::decode($cursor)) ?? throw new InvalidArgumentException('The activity history cursor cannot be read.');
        }

        $query = $this->counted();

        if ($after !== null) {
            $query->where($query->qualifyColumn('id'), '>', $after);
        }

        // One more than asked tells whether there is another page, without a cursor to an empty one
        $users = $query->limit($limit + 1)->get();
        $hasMore = $users->count() > $limit;
        $page = $users->take($limit)->values();
        $facts = $this->factsOf($page);

        $subjects = [];
        foreach ($page as $user) {
            $id = strtolower((string) $user->id);

            if (isset($facts[$id])) {
                $subjects[] = new ActivitySubjectFactsDTO($id, [$facts[$id]]);
            }
        }

        $last = $page->last();

        return new ActivityHistoryPageDTO(
            $subjects,
            $hasMore && $last instanceof UserModel ? OpaqueCursor::encode(strtolower((string) $last->id)) : null,
        );
    }

    public function describe(array $subjectIds, ?Authenticatable $viewer): array
    {
        $ids = array_values(array_unique(array_filter(array_map($this->accountId(...), $subjectIds))));

        if ($ids === []) {
            return [];
        }

        $users = $this->counted()->whereKey($ids)->get();
        $rows = $this->profiles->findMany($ids);

        $descriptions = [];
        foreach ($users as $user) {
            $row = $rows[strtolower((string) $user->id)] ?? null;

            if ($row !== null) {
                $descriptions[strtolower((string) $user->id)] = PublicProfileService::describe($row, $user);
            }
        }

        // The profile's own rule: a hidden profile shows neither its name nor a link to it
        $visible = array_flip($this->access->visibleAmong(array_values($descriptions), $viewer));
        $described = [];

        foreach ($users as $user) {
            $id = strtolower((string) $user->id);
            $profile = $descriptions[$id] ?? null;

            if ($profile instanceof PublicProfileDTO && isset($visible[$id]) && $profile->shownPublicName() !== null) {
                $described[$id] = new ActivitySubjectDTO(
                    $id,
                    $this->titleOf($profile->shownPublicName()),
                    route('profiles.show', ['handle' => $profile->handle], false),
                );

                continue;
            }

            $described[$id] = new ActivitySubjectDTO($id, $this->anonymousTitle());
        }

        return $described;
    }

    /**
     * @param  Collection<int, UserModel>  $users
     * @return array<string, ActivityFactDTO> account id (lower case) => its fact
     */
    private function factsOf(Collection $users): array
    {
        if ($users->isEmpty()) {
            return [];
        }

        $instants = $this->occurredAt($users);
        $facts = [];

        foreach ($users as $user) {
            $id = strtolower((string) $user->id);

            if (isset($instants[$id])) {
                $facts[$id] = new ActivityFactDTO($id, ActivityRole::Owner, $instants[$id]);
            }
        }

        return $facts;
    }

    /**
     * The account id in lower case, null for anything that cannot be one (it would abort a
     * PostgreSQL transaction).
     */
    private function accountId(string $subjectId): ?string
    {
        return Str::isUuid($subjectId) ? strtolower($subjectId) : null;
    }
}
