<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Application\Profiles\Services\ProfileAccessRule;
use App\Application\Profiles\Services\ProfileBadgesInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Profiles\ProfileBadgeCollector;
use App\Infrastructure\Profiles\StoredProfiles;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The featured badges of a page's authors (core#776): the profile rows and the accounts in one query
 * each, ProfileAccessRule for all of them at once, then every source once with the visible ones. The
 * request's rows and the collector are bound scoped, so they are asked for on each call: a module may
 * keep this service longer than one request or job.
 */
final readonly class ProfileBadges implements ProfileBadgesInterface
{
    public function __construct(
        private ProfileAccessRule $access,
        private Container $container,
        private LoggerInterface $log,
    ) {}

    public function forAuthors(array $userIds, ?Authenticatable $viewer): array
    {
        try {
            $ids = $this->ids($userIds);

            if ($ids === []) {
                return [];
            }

            $collector = $this->container->make(ProfileBadgeCollector::class);

            // Nothing to show: nothing read
            if (! $collector->hasSources()) {
                return [];
            }

            $rows = $this->container->make(StoredProfiles::class)->findMany($ids);

            if ($rows === []) {
                return [];
            }

            $owners = UserModel::withTrashed()->whereKey(array_keys($rows))->get()->keyBy(
                static fn (UserModel $owner): string => strtolower((string) $owner->id),
            );

            $profiles = [];
            foreach ($rows as $userId => $row) {
                $owner = $owners->get($userId);

                if ($owner instanceof UserModel) {
                    $profiles[] = PublicProfileService::describe($row, $owner);
                }
            }

            // Only now, once the access rule let this person see each profile
            return $collector->collect($this->access->visibleAmong($profiles, $viewer));
        } catch (Throwable $exception) {
            // Only the class: a message may carry what a person can see
            $this->log->warning('The badges of the authors of a page could not be read; none is shown.', [
                'exception' => $exception::class,
            ]);

            return [];
        }
    }

    /**
     * Account ids in lower case (as they are stored: PostgreSQL's uuid ignores case and SQLite's text
     * does not), each once; anything else is never asked about, since it would abort a PostgreSQL
     * transaction.
     *
     * @param  array<mixed>  $userIds
     * @return list<string>
     */
    private function ids(array $userIds): array
    {
        $ids = [];
        foreach ($userIds as $userId) {
            if (is_string($userId) && Str::isUuid($userId)) {
                $ids[strtolower($userId)] = true;
            }
        }

        return array_keys($ids);
    }
}
