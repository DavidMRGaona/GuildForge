<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Application\Profiles\DTOs\PublicProfileDTO;
use App\Application\Profiles\DTOs\VisibleProfileDTO;
use App\Application\Profiles\Services\ProfileAccessRule;
use App\Application\Profiles\Services\VisibleProfilesInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Profiles\StoredProfiles;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The public profiles of many accounts one person may see (core#840): the profile rows and their
 * accounts in one query each, described as the profile page describes them, then ProfileAccessRule for
 * all of them at once. The request's rows are bound scoped, so they are asked for on each call: a module
 * may keep this service longer than one request or job.
 */
final readonly class VisibleProfiles implements VisibleProfilesInterface
{
    public function __construct(
        private ProfileAccessRule $access,
        private Container $container,
        private LoggerInterface $log,
    ) {}

    public function among(array $userIds, ?Authenticatable $viewer): array
    {
        $ids = $this->ids($userIds);

        if (count($ids) > self::MAX_IDS) {
            throw new InvalidArgumentException(sprintf('At most %d accounts can be asked about at once.', self::MAX_IDS));
        }

        if ($ids === []) {
            return [];
        }

        try {
            $rows = $this->container->make(StoredProfiles::class)->findMany($ids);

            if ($rows === []) {
                return [];
            }

            // Deactivated accounts too: the rule tells them apart
            $owners = UserModel::withTrashed()->whereKey(array_keys($rows))->get()->keyBy(
                static fn (UserModel $owner): string => strtolower((string) $owner->id),
            );

            /** @var array<string, array{profile: PublicProfileDTO, avatar: string|null}> $described */
            $described = [];
            foreach ($rows as $userId => $row) {
                $owner = $owners->get($userId);

                if ($owner instanceof UserModel) {
                    $described[$userId] = [
                        'profile' => PublicProfileService::describe($row, $owner),
                        'avatar' => $owner->avatar_public_id,
                    ];
                }
            }

            $visible = $this->access->visibleAmong(
                array_values(array_map(static fn (array $entry): PublicProfileDTO => $entry['profile'], $described)),
                $viewer,
            );

            // Only now, once the access rule let this person see each profile; in the order of the ids
            $profiles = [];
            foreach ($visible as $userId) {
                ['profile' => $profile, 'avatar' => $avatar] = $described[strtolower($userId)];

                $profiles[] = new VisibleProfileDTO(
                    $profile->userId,
                    $profile->handle,
                    $profile->shownPublicName(),
                    is_string($avatar) && trim($avatar) !== '' ? $avatar : null,
                );
            }

            return $profiles;
        } catch (Throwable $exception) {
            // Only the class: a message may carry what a person can see
            $this->log->warning('The visible profiles of a list of accounts could not be read; none is shown.', [
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
