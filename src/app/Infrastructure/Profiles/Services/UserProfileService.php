<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Application\Media\Services\MediaFileReleaserInterface;
use App\Application\Profiles\DTOs\ProfileCreationResultDTO;
use App\Application\Profiles\DTOs\ProfileUpdateDTO;
use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Domain\Profiles\Exceptions\InvalidProfileHandleException;
use App\Domain\Profiles\Exceptions\ProfileHandleChangeTooSoonException;
use App\Domain\Profiles\Exceptions\ProfileHandleTakenException;
use App\Domain\Profiles\Exceptions\ProfileHandleUnavailableException;
use App\Domain\Profiles\ProfileHandleAvailability;
use App\Domain\Profiles\ProfileHandleChangeRule;
use App\Domain\Profiles\ProfileVisibility;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileHandleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\ProfileHandleCandidates;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * The history decides who holds an address: giving one writes its row (primary key `handle_hash`) in the
 * same transaction as the profile, so two accounts racing for it can never both get it, and retiring only
 * stamps `retired_at` on the owner's own row.
 *
 * Every try runs in its own transaction, nested as a savepoint when the caller already holds one: a clash
 * rolls back only that try, so the account being saved around it (registration, the panel) never fails in
 * PostgreSQL because of an aborted transaction.
 *
 * The owner's own choice of an address (core#743) goes through the same claim(): a reserved word, an
 * address somebody else holds or held, or one taken meanwhile is refused alike.
 */
final readonly class UserProfileService implements UserProfileServiceInterface
{
    /** Where banners are stored, by year and month; ProfileBannerMediaSource claims it */
    public const string BANNER_FOLDER = 'profiles/banners/';

    /** The banner types accepted, by what their content is */
    private const array BANNER_EXTENSIONS = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

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

        // By id, so rows created along the way never shift the next chunk
        $missing->select(['users.id', 'users.display_name'])->chunkById($chunk, function (Collection $users) use ($dryRun, &$created, &$failed): void {
            foreach ($users as $user) {
                $userId = (string) $user->getKey();

                if ($dryRun) {
                    $this->wouldFind($user->display_name, $userId) ? $created++ : $failed++;

                    continue;
                }

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
                        $this->wouldFind($profile->user?->display_name, $profile->user_id) ? $realigned++ : $failed++;

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

    public function handleAvailability(string $userId, string $handle): ProfileHandleAvailability
    {
        if (UserProfileModel::query()->whereKey($userId)->value('handle') === ProfileHandle::normalise($handle)) {
            return ProfileHandleAvailability::Current;
        }

        try {
            $wanted = ProfileHandle::fromString($handle);
        } catch (InvalidProfileHandleException $exception) {
            // A reserved word is said to be unavailable, like an address somebody holds
            return $exception->reserved ? ProfileHandleAvailability::Unavailable : ProfileHandleAvailability::Malformed;
        }

        return $this->isFreeFor($wanted, $userId) ? ProfileHandleAvailability::Available : ProfileHandleAvailability::Unavailable;
    }

    public function nextHandleChangeAt(string $userId): ?DateTimeImmutable
    {
        $profile = UserProfileModel::query()->find($userId);

        return $profile instanceof UserProfileModel
            ? ProfileHandleChangeRule::nextChangeAt($profile->handle_changed_at, now())
            : null;
    }

    public function update(string $userId, ProfileUpdateDTO $update): void
    {
        $wanted = ProfileHandle::normalise($update->handle);

        try {
            DB::transaction(function () use ($userId, $update, $wanted): void {
                $profile = UserProfileModel::query()->lockForUpdate()->find($userId);

                if (! $profile instanceof UserProfileModel) {
                    throw new RuntimeException('The account has no public profile to update.');
                }

                $attributes = [
                    'visibility' => $update->visibility,
                    'bio' => $update->bio,
                    'show_membership' => $update->showMembership,
                    'layout' => $profile->layout->chosen($update->sections, $update->stats, $update->offeredSections, $update->offeredStats),
                ];

                // The current address is kept as it is, never checked again as a new one: one that became a
                // reserved word since it was given must not block the rest of the save
                if ($wanted !== $profile->handle) {
                    $chosen = ProfileHandle::fromString($wanted);

                    // Read under the lock: two saves at once never both change it
                    $next = ProfileHandleChangeRule::nextChangeAt($profile->handle_changed_at, now());

                    if ($next instanceof DateTimeImmutable) {
                        throw ProfileHandleChangeTooSoonException::until($next);
                    }

                    if (! $this->isFreeFor($chosen, $userId)) {
                        throw ProfileHandleTakenException::create();
                    }

                    $this->claim($chosen, $userId);
                    $this->retire($profile->handle, $userId);
                    $attributes['handle'] = $chosen->value;
                    $attributes['handle_chosen'] = true;
                    $attributes['handle_changed_at'] = now();
                }

                $profile->update($attributes);
            });
        } catch (UniqueConstraintViolationException) {
            // Taken by somebody else since it was checked: the history decided
            throw ProfileHandleTakenException::create();
        }
    }

    public function replaceBanner(string $userId, UploadedFile $banner): string
    {
        if (! $this->hasProfile($userId)) {
            throw new RuntimeException('The account has no public profile for a banner.');
        }

        // Named by what the bytes are, never by the name the browser sent
        $extension = self::BANNER_EXTENSIONS[(string) $banner->getMimeType()]
            ?? throw new RuntimeException('A banner must be a JPEG, PNG or WebP image.');
        $path = self::BANNER_FOLDER.now()->format('Y/m').'/'.Str::uuid()->toString().'.'.$extension;

        // Through the disk, like every upload: an impersonation's safety net discards it
        if (Storage::disk('images')->put($path, $banner->getContent()) === false) {
            throw new RuntimeException('The banner could not be stored.');
        }

        try {
            $previous = DB::transaction(static function () use ($userId, $path): ?string {
                $profile = UserProfileModel::query()->lockForUpdate()->find($userId);

                if (! $profile instanceof UserProfileModel) {
                    throw new RuntimeException('The account has no public profile for a banner.');
                }

                $previous = $profile->banner_public_id;
                $profile->update(['banner_public_id' => $path]);

                return $previous;
            });
        } catch (Throwable $exception) {
            // Nobody points at it: the library may delete it
            $this->mediaReleaser->release($path);

            throw $exception;
        }

        $this->releaseBanner($previous);

        return $path;
    }

    public function removeBanner(string $userId): void
    {
        $previous = DB::transaction(static function () use ($userId): ?string {
            $profile = UserProfileModel::query()->lockForUpdate()->find($userId);

            if (! $profile instanceof UserProfileModel || $profile->banner_public_id === null) {
                return null;
            }

            $previous = $profile->banner_public_id;
            $profile->update(['banner_public_id' => null]);

            return $previous;
        });

        $this->releaseBanner($previous);
    }

    public function resetContent(string $userId): bool
    {
        $reset = DB::transaction(static function () use ($userId): array {
            $profile = UserProfileModel::query()->lockForUpdate()->find($userId);

            if (! $profile instanceof UserProfileModel || (blank($profile->bio) && blank($profile->banner_public_id))) {
                return [false, null];
            }

            $banner = $profile->banner_public_id;
            $profile->update(['bio' => null, 'banner_public_id' => null]);

            return [true, $banner];
        });

        // Only once nothing points at it any more
        $this->releaseBanner($reset[1]);

        return $reset[0];
    }

    private function releaseBanner(?string $banner): void
    {
        if (is_string($banner) && $banner !== '') {
            $this->mediaReleaser->release($banner);
        }
    }

    /**
     * For the dry run, without writing: whether some address is free for this account now. The random
     * candidates are drawn again, and a clash with another write meanwhile is only seen by the real run.
     */
    private function wouldFind(?string $publicName, string $userId): bool
    {
        foreach (ProfileHandleCandidates::for($publicName) as $handle) {
            if ($this->isFreeFor($handle, $userId)) {
                return true;
            }
        }

        return false;
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
