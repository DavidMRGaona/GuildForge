<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\StoredProfiles;
use Illuminate\Contracts\Auth\Authenticatable;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * The banner of a signed-in person's own public profile (core#764), drawn behind the band of their
 * account settings (`/perfil`) as it is on the profile page. Only the owner's own row is read, so no
 * access rule is needed: the owner always sees their own banner. An account without a row, deactivated
 * or anonymized has none.
 *
 * Never worth losing the settings page over: a failure is logged and the band stays plain.
 *
 * A class of its own rather than a method of UserProfileServiceInterface: modules implement that
 * interface in their tests, and a new method would break them.
 */
final readonly class OwnProfileBanner
{
    public function __construct(
        private StoredProfiles $rows,
        private LoggerInterface $log,
    ) {}

    public function for(?Authenticatable $user): ?string
    {
        if (! $user instanceof UserModel || $user->trashed() || $user->anonymized_at !== null) {
            return null;
        }

        try {
            $row = $this->rows->find((string) $user->getAuthIdentifier());
        } catch (Throwable $exception) {
            $this->log->warning('[Profiles] The banner of the own profile could not be read; the band stays plain.', [
                'exception' => $exception::class,
            ]);

            return null;
        }

        $banner = $row instanceof UserProfileModel ? $row->banner_public_id : null;

        return $banner === null || $banner === '' ? null : $banner;
    }
}
