<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Images\AnimatedImageDetector;
use App\Infrastructure\Images\AvatarPath;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Console\Command;
use Illuminate\Contracts\Console\Isolatable;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Gives animated avatars stored before 3.10.1 their marked name (AvatarPath), so the frontend
 * asks for animated delivery. Until then the profile stored "users/<id>/<uuid>.jpg" and the
 * panel "avatars/<Y>/<m>/<uuid>.<extension sent by the browser>", whatever the bytes; every
 * user with an avatar is checked, and only the animated ones are renamed.
 *
 * The marker is part of the public id, so the image is copied (original bytes, never
 * re-encoded) under the new path, the user is pointed at it and only then the old file is
 * deleted. Idempotent: marked avatars are skipped without being read, static ones are left
 * untouched (but read again on every run). Soft-deleted users are skipped: deleting them
 * already removed their image.
 *
 * Isolated by default (a second run at the same time stops at once), and a copy is only
 * removed when the user does not point at it, so overlapping runs never delete the avatar.
 */
final class MarkAnimatedAvatarsCommand extends Command implements Isolatable
{
    protected $signature = 'users:mark-animated-avatars
                            {--dry-run : List the animated avatars that would be renamed without changing anything}';

    protected $description = 'Rename animated avatars stored before they were marked, so they are delivered animated';

    /** @var bool */
    protected $isolated = true;

    protected $isolatedExitCode = self::FAILURE;

    private int $failed = 0;

    private int $leftBehind = 0;

    public function handle(AvatarPath $paths, AnimatedImageDetector $animation): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('images');
        $checked = $renamed = $marked = 0;
        // The same instance serves every call within a process
        $this->failed = $this->leftBehind = 0;

        $users = UserModel::query()->whereNotNull('avatar_public_id')->select(['id', 'avatar_public_id'])->lazyById(100);

        foreach ($users as $user) {
            $old = (string) $user->avatar_public_id;

            if (AvatarPath::isAnimated($old)) {
                $marked++;

                continue;
            }

            $checked++;
            $contents = $this->read($disk, $old);

            if ($contents === null) {
                $this->reportFailure("{$user->id}: {$old} could not be read; left as it is.");

                continue;
            }

            if (! $animation->isAnimated($contents)) {
                continue;
            }

            $new = $paths->forStored($old, $contents);
            $this->line("  {$user->id}: {$old} -> {$new}");

            if ($dryRun || $this->rename($disk, (string) $user->id, $old, $new, $contents)) {
                $renamed++;
            }
        }

        $verb = $dryRun ? 'would be renamed' : 'renamed';
        $this->components->info("{$checked} avatar(s) checked, {$renamed} animated {$verb}, {$marked} already marked, {$this->failed} failed, {$this->leftBehind} unused file(s) left behind.");

        return $this->failed > 0 || $this->leftBehind > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function read(Filesystem $disk, string $path): ?string
    {
        try {
            return $disk->get($path);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Copy, re-point, then delete the old file. True when the user ends up on the marked path.
     */
    private function rename(Filesystem $disk, string $userId, string $old, string $new, string $contents): bool
    {
        try {
            $stored = $disk->put($new, $contents);
            $reason = 'the disk refused it';
        } catch (Throwable $e) {
            $stored = false;
            $reason = $e::class;
        }

        if ($stored === false) {
            $this->reportFailure("{$userId}: {$new} could not be stored ({$reason}); left as it is.");

            return false;
        }

        // Query builder: no model events, so the old image is not deleted before the user
        // points at the copy. Guarded on the old value in case the avatar changed meanwhile
        $updated = UserModel::query()->whereKey($userId)->where('avatar_public_id', $old)->update(['avatar_public_id' => $new]);

        if ($updated === 0) {
            $current = UserModel::query()->whereKey($userId)->value('avatar_public_id');

            if ($current === $new) {
                // Another run renamed it first: the copy is the avatar, never delete it
                $this->components->warn("{$userId}: already renamed by another run.");

                return true;
            }

            $this->reportFailure("{$userId}: the avatar changed while renaming; left as it is.");
            $this->deleteUnused($disk, $userId, $new);

            return false;
        }

        $this->deleteUnused($disk, $userId, $old);

        return true;
    }

    private function deleteUnused(Filesystem $disk, string $userId, string $path): void
    {
        try {
            $deleted = $disk->delete($path);
            $reason = 'the disk refused it';
        } catch (Throwable $e) {
            $deleted = false;
            $reason = $e::class;
        }

        if ($deleted === false) {
            $this->leftBehind++;
            $this->components->warn("{$userId}: {$path} could not be deleted ({$reason}); it is no longer used.");
        }
    }

    private function reportFailure(string $message): void
    {
        $this->failed++;
        $this->components->error($message);
    }
}
