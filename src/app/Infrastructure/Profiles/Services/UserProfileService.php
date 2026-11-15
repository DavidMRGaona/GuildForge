<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Application\Profiles\DTOs\ProfileCreationResultDTO;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Domain\Profiles\Exceptions\ProfileHandleUnavailableException;
use App\Domain\Profiles\ProfileVisibility;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileHandleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\ProfileHandleCandidates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The history decides who holds an address: giving one writes its row (primary key `handle_hash`) in the
 * same transaction as the profile, so two accounts racing for it can never both get it, and retiring only
 * stamps `retired_at` on the owner's own row.
 *
 * Every try runs in its own transaction, nested as a savepoint when the caller already holds one: a clash
 * rolls back only that try, so the account being saved around it (registration, the panel) never fails in
 * PostgreSQL because of an aborted transaction.
 */
final readonly class UserProfileService implements UserProfileServiceInterface
{
    public function __construct(
        private MediaFileReleaserInterface $mediaReleaser,
    ) {}

    public function createFor(string $userId): bool
    {
        $user = UserModel::query()->withTrashed()->find($userId);

        if (! $user instanceof UserModel || $user->anonymized_at !== null) {
            return false;
        }

        if ($this->hasProfile($userId)) {
            return false;
        }

        foreach (ProfileHandleCandidates::for($user->display_name) as $handle) {
            if (! $this->isFreeFor($handle, $userId)) {
                continue;
            }

            try {
                DB::transaction(function () use ($handle, $userId): void {
                    $this->claim($handle, $userId);
                    UserProfileModel::query()->create([
                        'user_id' => $userId,
                        'handle' => $handle->value,
                        'handle_chosen' => false,
                        'visibility' => ProfileVisibility::default(),
                        'show_membership' => false,
                    ]);
                });

                return true;
            } catch (UniqueConstraintViolationException) {
                // Another process created this account's row first, or took the address since it was checked
                if ($this->hasProfile($userId)) {
                    return false;
                }
            }
        }

        throw ProfileHandleUnavailableException::forUser($userId);
    }

    public function followPublicName(string $userId): bool
    {
        $user = UserModel::query()->withTrashed()->find($userId);
        $profile = UserProfileModel::query()->find($userId);

        if (! $user instanceof UserModel || $user->anonymized_at !== null || ! $profile instanceof UserProfileModel || $profile->handle_chosen) {
            return false;
        }

        $current = $profile->handle;

        if (ProfileHandleCandidates::follows($current, $user->display_name)) {
            return false;
        }

        foreach (ProfileHandleCandidates::for($user->display_name) as $handle) {
            if (! $this->isFreeFor($handle, $userId)) {
                continue;
            }

            try {
                return DB::transaction(function () use ($handle, $userId, $current): bool {
                    $locked = UserProfileModel::query()->lockForUpdate()->find($userId);

                    // Changed meanwhile (chosen by the owner, moved by another save): left as it is now
                    if (! $locked instanceof UserProfileModel || $locked->handle_chosen || $locked->handle !== $current) {
                        return false;
                    }

                    $this->claim($handle, $userId);
                    $this->retire($current, $userId);
                    $locked->update(['handle' => $handle->value]);

                    return true;
                });
            } catch (UniqueConstraintViolationException) {
                // Taken since it was checked: the next candidate
            }
        }

        throw ProfileHandleUnavailableException::forUser($userId);
    }

    public function forget(string $userId): void
    {
        DB::transaction(function () use ($userId): void {
            $profile = UserProfileModel::query()->lockForUpdate()->find($userId);

            if ($profile instanceof UserProfileModel) {
                $this->retire($profile->handle, $userId);
                $banner = $profile->banner_public_id;
                $profile->delete();

                if (is_string($banner) && $banner !== '') {
                    $this->mediaReleaser->release($banner);
                }
            }

            UserProfileHandleModel::query()->where('user_id', $userId)->whereNull('retired_at')->update(['retired_at' => now()]);
            UserProfileHandleModel::query()->where('user_id', $userId)->update(['handle' => null, 'user_id' => null]);
        });
    }

    public function createMissing(bool $dryRun, int $chunk): ProfileCreationResultDTO
    {
        $chunk = max(1, $chunk);
        $created = 0;
        $realigned = 0;
        $failed = 0;

        $missing = UserModel::query()
            ->withTrashed()
            ->whereNull('anonymized_at')
            ->whereNotExists(static function (QueryBuilder $profiles): void {
                $profiles->selectRaw('1')->from('user_profiles')->whereColumn('user_profiles.user_id', 'users.id');
            });

        if ($dryRun) {
            $created = $missing->count();
        } else {
            // By id, so rows created along the way never shift the next chunk
            $missing->select('users.id')->chunkById($chunk, function (Collection $users) use (&$created, &$failed): void {
                foreach ($users as $user) {
                    $userId = (string) $user->getKey();

                    try {
                        if ($this->createFor($userId)) {
                            $created++;
                        }
                    } catch (Throwable $e) {
                        $failed++;
                        $this->logFailure('The public profile of an account could not be created.', $userId, $e);
                    }
                }
            }, 'users.id', 'id');
        }

        // Addresses left behind by a failed move when the public name changed
        UserProfileModel::query()
            ->where('handle_chosen', false)
            ->whereHas('user', static function (Builder $users): void {
                $users->whereNull('anonymized_at');
            })
            ->with('user:id,display_name,anonymized_at')
            ->chunkById($chunk, function (Collection $profiles) use ($dryRun, &$realigned, &$failed): void {
                foreach ($profiles as $profile) {
                    if (ProfileHandleCandidates::follows($profile->handle, $profile->user?->display_name)) {
                        continue;
                    }

                    if ($dryRun) {
                        $realigned++;

                        continue;
                    }

                    try {
                        if ($this->followPublicName($profile->user_id)) {
                            $realigned++;
                        }
                    } catch (Throwable $e) {
                        $failed++;
                        $this->logFailure('The public profile address of an account could not follow its public name.', $profile->user_id, $e);
                    }
                }
            }, 'user_id');

        if (! $dryRun) {
            Log::info('[Profiles] Missing public profiles created.', ['created' => $created, 'realigned' => $realigned, 'failed' => $failed]);
        }

        return new ProfileCreationResultDTO($created, $realigned, $failed);
    }

    /**
     * Read again after a clash: another process may have created the row meanwhile.
     *
     * @phpstan-impure
     */
    private function hasProfile(string $userId): bool
    {
        return UserProfileModel::query()->whereKey($userId)->exists();
    }

    /**
     * Free when nobody ever held it, or when this account did: an owner may get back an address they used
     * before. Only a quick check: claim() is what decides.
     */
    private function isFreeFor(ProfileHandle $handle, string $userId): bool
    {
        $given = UserProfileHandleModel::query()->find($handle->hash());

        if ($given instanceof UserProfileHandleModel) {
            return $given->user_id === $userId;
        }

        return ! UserProfileModel::query()->where('handle', $handle->value)->where('user_id', '!=', $userId)->exists();
    }

    /**
     * Writes the address to the history as this account's current one, or takes back one of its own.
     *
     * @throws UniqueConstraintViolationException when anybody else ever held it
     */
    private function claim(ProfileHandle $handle, string $userId): void
    {
        $reclaimed = UserProfileHandleModel::query()
            ->whereKey($handle->hash())
            ->where('user_id', $userId)
            ->update(['retired_at' => null]);

        if ($reclaimed === 0) {
            UserProfileHandleModel::query()->insert([
                'handle_hash' => $handle->hash(),
                'handle' => $handle->value,
                'user_id' => $userId,
                'retired_at' => null,
            ]);
        }
    }

    /**
     * Stamps the account's own row of an address as retired. Never touches anybody else's row: a missing
     * one is written only if the hash is still free.
     */
    private function retire(string $handle, string $userId): void
    {
        $hash = ProfileHandle::hashOf($handle);

        $retired = UserProfileHandleModel::query()
            ->whereKey($hash)
            ->where('user_id', $userId)
            ->update(['retired_at' => now()]);

        if ($retired === 0) {
            UserProfileHandleModel::query()->insertOrIgnore([
                'handle_hash' => $hash,
                'handle' => $handle,
                'user_id' => $userId,
                'retired_at' => now(),
            ]);
        }
    }

    private function logFailure(string $message, string $userId, Throwable $e): void
    {
        Log::warning('[Profiles] '.$message, ['user_id' => $userId, 'exception' => $e]);
    }
}
