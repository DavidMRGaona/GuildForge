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
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Every write runs in its own transaction, nested as a savepoint when the caller already holds one: a
 * clash on an address rolls back only that try, so the account being saved around it (registration,
 * the panel) never fails in PostgreSQL because of an aborted transaction.
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
                    $this->reclaim($handle, $userId);
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

    public function followPublicName(string $userId): void
    {
        $user = UserModel::query()->withTrashed()->find($userId);
        $profile = UserProfileModel::query()->find($userId);

        if (! $user instanceof UserModel || $user->anonymized_at !== null || ! $profile instanceof UserProfileModel || $profile->handle_chosen) {
            return;
        }

        $current = $profile->handle;

        // Still no public name: the random address stays
        if (ProfileHandleCandidates::baseOf($user->display_name) === '' && ProfileHandleCandidates::isRandom($current)) {
            return;
        }

        foreach (ProfileHandleCandidates::for($user->display_name) as $handle) {
            // The address the name gives is already this one (`ana-2` while `ana` is someone else's)
            if ($handle->value === $current) {
                return;
            }

            if (! $this->isFreeFor($handle, $userId)) {
                continue;
            }

            try {
                // A row changed meanwhile (chosen by the owner, moved by another save) is left as it is now
                DB::transaction(function () use ($handle, $userId, $current): void {
                    $updated = UserProfileModel::query()
                        ->whereKey($userId)
                        ->where('handle', $current)
                        ->where('handle_chosen', false)
                        ->update(['handle' => $handle->value, 'updated_at' => now()]);

                    if ($updated > 0) {
                        $this->reclaim($handle, $userId);
                        $this->retire($current, $userId);
                    }
                });

                return;
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
                $this->retire($profile->handle, null);
                $banner = $profile->banner_public_id;
                $profile->delete();

                if (is_string($banner) && $banner !== '') {
                    $this->mediaReleaser->release($banner);
                }
            }

            UserProfileHandleModel::query()->where('user_id', $userId)->update(['handle' => null, 'user_id' => null]);
        });
    }

    public function createMissing(bool $dryRun, int $chunk): ProfileCreationResultDTO
    {
        $query = UserModel::query()
            ->withTrashed()
            ->whereNull('anonymized_at')
            ->whereNotExists(static function (QueryBuilder $profiles): void {
                $profiles->selectRaw('1')->from('user_profiles')->whereColumn('user_profiles.user_id', 'users.id');
            });

        if ($dryRun) {
            return new ProfileCreationResultDTO($query->count());
        }

        $created = 0;
        $failed = 0;

        $this->eachMissing($query, max(1, $chunk), function (string $userId) use (&$created, &$failed): void {
            try {
                if ($this->createFor($userId)) {
                    $created++;
                }
            } catch (Throwable $e) {
                $failed++;
                Log::warning('[Profiles] The public profile of an account could not be created.', [
                    'user_id' => $userId,
                    'exception' => $e,
                ]);
            }
        });

        Log::info('[Profiles] Missing public profiles created.', ['created' => $created, 'failed' => $failed]);

        return new ProfileCreationResultDTO($created, $failed);
    }

    /**
     * @param  Builder<UserModel>  $query
     * @param  Closure(string): void  $callback
     */
    private function eachMissing(Builder $query, int $chunk, Closure $callback): void
    {
        // By id, so rows created along the way never shift the next chunk
        $query->select('users.id')->chunkById($chunk, static function ($users) use ($callback): void {
            foreach ($users as $user) {
                $callback((string) $user->getKey());
            }
        }, 'users.id', 'id');
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
     * Free when nobody holds it now and it is not in the history, unless the history row is this
     * account's own: an owner may get back an address they used before.
     */
    private function isFreeFor(ProfileHandle $handle, string $userId): bool
    {
        if (UserProfileModel::query()->where('handle', $handle->value)->where('user_id', '!=', $userId)->exists()) {
            return false;
        }

        $retired = UserProfileHandleModel::query()->find($handle->hash());

        return ! $retired instanceof UserProfileHandleModel || $retired->user_id === $userId;
    }

    /**
     * Takes an address back out of this account's own history.
     */
    private function reclaim(ProfileHandle $handle, string $userId): void
    {
        UserProfileHandleModel::query()->whereKey($handle->hash())->where('user_id', $userId)->delete();
    }

    /**
     * Keeps an address in the history: with its owner while it should redirect, as a bare hash otherwise.
     */
    private function retire(string $handle, ?string $userId): void
    {
        UserProfileHandleModel::query()->updateOrCreate(
            ['handle_hash' => ProfileHandle::hashOf($handle)],
            ['handle' => $userId === null ? null : $handle, 'user_id' => $userId, 'retired_at' => now()],
        );
    }
}
