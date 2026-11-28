<?php

declare(strict_types=1);

namespace Tests\Support\Profiles;

use App\Application\Profiles\DTOs\VisibleProfileDTO;
use App\Application\Profiles\Services\VisibleProfilesInterface;
use App\Domain\Profiles\Exceptions\VisibleProfilesUnavailableException;
use Closure;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * VisibleProfilesInterface for tests (core#840): it reads the ids as the core does (lower case, each
 * once, anything that is not an account id ignored, more than MAX_IDS refused) and answers, in their
 * order, the profiles it was given among them. Every call is remembered with the ids it read.
 *
 * Without a closure every profile given is visible to everybody; with one, the closure decides which
 * of them a viewer may see. Bind it in place of the core's:
 *
 *     $this->app->instance(VisibleProfilesInterface::class, new FakeVisibleProfiles([
 *         FakeVisibleProfiles::profile($ana->id, 'ana-garcia', 'Ana García'),
 *     ]));
 *
 * failing() makes it fail to read as the core can (core#871): among() then gives no profile and
 * amongOrFail() throws VisibleProfilesUnavailableException, after the ids are read and the call kept.
 */
final class FakeVisibleProfiles implements VisibleProfilesInterface
{
    /** @var list<array{userIds: list<string>, viewer: Authenticatable|null}> */
    public array $calls = [];

    /** @var array<string, VisibleProfileDTO> */
    private array $profiles = [];

    private bool $fails = false;

    /**
     * @param  list<VisibleProfileDTO>  $profiles
     * @param  (Closure(VisibleProfileDTO, Authenticatable|null): bool)|null  $sees
     */
    public function __construct(
        array $profiles = [],
        private readonly ?Closure $sees = null,
    ) {
        foreach ($profiles as $profile) {
            $this->profiles[$profile->userId] = $profile;
        }
    }

    public static function profile(string $userId, string $handle = 'ana-garcia', ?string $publicName = 'Ana García', ?string $avatarPublicId = null): VisibleProfileDTO
    {
        return new VisibleProfileDTO($userId, $handle, $publicName, $avatarPublicId);
    }

    /** From now on every read fails, as the core's does when the database cannot be read */
    public function failing(): self
    {
        $this->fails = true;

        return $this;
    }

    public function among(array $userIds, ?Authenticatable $viewer): array
    {
        try {
            return $this->amongOrFail($userIds, $viewer);
        } catch (VisibleProfilesUnavailableException) {
            return [];
        }
    }

    public function amongOrFail(array $userIds, ?Authenticatable $viewer): array
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

        $this->calls[] = ['userIds' => $ids, 'viewer' => $viewer];

        if ($this->fails) {
            throw VisibleProfilesUnavailableException::unreadable();
        }

        $visible = [];
        foreach ($ids as $userId) {
            $profile = $this->profiles[$userId] ?? null;

            if ($profile instanceof VisibleProfileDTO && (! $this->sees instanceof Closure || ($this->sees)($profile, $viewer))) {
                $visible[] = $profile;
            }
        }

        return $visible;
    }
}
