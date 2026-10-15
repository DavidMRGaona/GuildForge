<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Images\AnimatedImageDetector;
use App\Infrastructure\Images\AvatarPath;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Gives animated avatars stored before 3.10.1 ("users/<id>/<uuid>.jpg" whatever their bytes)
 * their marked name (AvatarPath), so the frontend asks for animated delivery.
 *
 * The marker is part of the public id, so the image is copied (original bytes, never
 * re-encoded) under the new path, the user is pointed at it and only then the old file is
 * deleted. Idempotent: marked avatars are skipped without being read, static ones are left
 * untouched. Soft-deleted users are skipped: deleting them already removed their image.
 */
final class MarkAnimatedAvatarsCommand extends Command
{
    protected $signature = 'users:mark-animated-avatars
                            {--dry-run : List the animated avatars that would be renamed without changing anything}';

    protected $description = 'Rename animated avatars stored before they were marked, so they are delivered animated';

    public function handle(AvatarPath $paths, AnimatedImageDetector $animation): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $disk = Storage::disk('images');
        $checked = $renamed = $marked = $failed = 0;

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
                $this->components->error("{$user->id}: {$old} could not be read; left as it is.");
                $failed++;

                continue;
            }

            if (! $animation->isAnimated($contents)) {
                continue;
            }

            $new = $paths->forStored($old, $contents);
            $this->line("  {$user->id}: {$old} -> {$new}");

            if ($dryRun) {
                $renamed++;

                continue;
            }

            if ($this->rename($disk, (string) $user->id, $old, $new, $contents)) {
                $renamed++;
            } else {
                $failed++;
            }
        }

        $verb = $dryRun ? 'would be renamed' : 'renamed';
        $this->components->info("{$checked} avatar(s) checked, {$renamed} animated {$verb}, {$marked} already marked, {$failed} failed.");

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function read(Filesystem $disk, string $path): ?string
    {
        try {
            return $disk->get($path);
        } catch (Throwable) {
            return null;
        }
    }

    private function rename(Filesystem $disk, string $userId, string $old, string $new, string $contents): bool
    {
        try {
            $stored = $disk->put($new, $contents);
        } catch (Throwable $e) {
            $stored = false;
            $this->components->error("{$userId}: {$new} could not be stored (".$e::class.'); left as it is.');
        }

        if ($stored === false) {
            return false;
        }

        // Query builder: no model events, so the old image is not deleted before the user
        // points at the copy. Guarded on the old value in case the avatar changed meanwhile
        $updated = UserModel::query()->whereKey($userId)->where('avatar_public_id', $old)->update(['avatar_public_id' => $new]);

        if ($updated === 0) {
            $disk->delete($new);
            $this->components->warn("{$userId}: the avatar changed while renaming; left as it is.");

            return false;
        }

        try {
            $disk->delete($old);
        } catch (Throwable $e) {
            $this->components->warn("{$userId}: {$old} could not be deleted (".$e::class.'); it is no longer used.');
        }

        return true;
    }
}
