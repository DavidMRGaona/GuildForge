<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Console\CacheCommandMutex;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Support\Images\ImageFixtures;
use Tests\TestCase;

/**
 * Avatars stored before animated ones were marked ("users/<id>/<uuid>.jpg" whatever their
 * bytes) are copied once under their marked name, so the frontend asks for animated
 * delivery. Static avatars are left exactly as they are.
 */
final class MarkAnimatedAvatarsCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    private FilesystemAdapter $disk;

    protected function setUp(): void
    {
        parent::setUp();

        $this->disk = Storage::fake('images');
    }

    public function test_an_animated_avatar_is_copied_under_its_marked_name_and_the_old_one_removed(): void
    {
        $gif = ImageFixtures::gif(2);
        $user = $this->userWithAvatar('users/u1/0d1c2b3a.jpg', $gif);

        $this->artisan('users:mark-animated-avatars')
            ->expectsOutputToContain('users/u1/0d1c2b3a.jpg -> users/u1/0d1c2b3a-animated.gif')
            ->expectsOutputToContain('1 avatar(s) checked, 1 animated renamed')
            ->assertSuccessful();

        $this->assertSame('users/u1/0d1c2b3a-animated.gif', $user->refresh()->avatar_public_id);
        $this->assertSame($gif, $this->disk->get('users/u1/0d1c2b3a-animated.gif'));
        $this->disk->assertMissing('users/u1/0d1c2b3a.jpg');
    }

    public function test_animated_png_and_webp_avatars_are_marked_with_their_real_extension(): void
    {
        $png = $this->userWithAvatar('users/u1/aaaa.jpg', ImageFixtures::png(2));
        $webp = $this->userWithAvatar('users/u2/bbbb.jpg', ImageFixtures::webp(2));

        $this->artisan('users:mark-animated-avatars')->assertSuccessful();

        $this->assertSame('users/u1/aaaa-animated.png', $png->refresh()->avatar_public_id);
        $this->assertSame('users/u2/bbbb-animated.webp', $webp->refresh()->avatar_public_id);
    }

    public function test_static_avatars_and_users_without_avatar_are_left_alone(): void
    {
        $jpeg = $this->userWithAvatar('users/u1/aaaa.jpg', ImageFixtures::jpeg());
        $gif = $this->userWithAvatar('users/u2/bbbb.jpg', ImageFixtures::gif(1));
        $withoutAvatar = UserModel::factory()->create(['avatar_public_id' => null]);
        $updatedAt = $jpeg->updated_at;

        $this->artisan('users:mark-animated-avatars')
            ->expectsOutputToContain('2 avatar(s) checked, 0 animated renamed')
            ->assertSuccessful();

        $this->assertSame('users/u1/aaaa.jpg', $jpeg->refresh()->avatar_public_id);
        $this->assertEquals($updatedAt, $jpeg->updated_at);
        $this->assertSame('users/u2/bbbb.jpg', $gif->refresh()->avatar_public_id);
        $this->assertNull($withoutAvatar->refresh()->avatar_public_id);
        $this->disk->assertExists(['users/u1/aaaa.jpg', 'users/u2/bbbb.jpg']);
    }

    public function test_dry_run_lists_the_avatars_without_changing_anything(): void
    {
        $user = $this->userWithAvatar('users/u1/0d1c2b3a.jpg', ImageFixtures::gif(2));

        $this->artisan('users:mark-animated-avatars', ['--dry-run' => true])
            ->expectsOutputToContain('users/u1/0d1c2b3a.jpg -> users/u1/0d1c2b3a-animated.gif')
            ->expectsOutputToContain('1 avatar(s) checked, 1 animated would be renamed')
            ->assertSuccessful();

        $this->assertSame('users/u1/0d1c2b3a.jpg', $user->refresh()->avatar_public_id);
        $this->disk->assertExists('users/u1/0d1c2b3a.jpg');
        $this->disk->assertMissing('users/u1/0d1c2b3a-animated.gif');
    }

    public function test_a_second_run_changes_nothing_and_does_not_read_marked_avatars(): void
    {
        $user = $this->userWithAvatar('users/u1/0d1c2b3a.jpg', ImageFixtures::gif(2));
        $this->artisan('users:mark-animated-avatars')->assertSuccessful();
        // A marked avatar is skipped by name: its bytes are not even read again
        $this->disk->delete('users/u1/0d1c2b3a-animated.gif');

        $this->artisan('users:mark-animated-avatars')
            ->expectsOutputToContain('0 avatar(s) checked, 0 animated renamed, 1 already marked')
            ->assertSuccessful();

        $this->assertSame('users/u1/0d1c2b3a-animated.gif', $user->refresh()->avatar_public_id);
    }

    public function test_an_avatar_that_cannot_be_read_is_reported_and_left_alone(): void
    {
        $missing = UserModel::factory()->create(['avatar_public_id' => 'users/u1/gone.jpg']);
        $animated = $this->userWithAvatar('users/u2/bbbb.jpg', ImageFixtures::gif(2));

        $this->artisan('users:mark-animated-avatars')
            ->expectsOutputToContain('users/u1/gone.jpg')
            ->expectsOutputToContain('1 failed')
            ->assertFailed();

        $this->assertSame('users/u1/gone.jpg', $missing->refresh()->avatar_public_id);
        $this->assertSame('users/u2/bbbb-animated.gif', $animated->refresh()->avatar_public_id);
    }

    public function test_soft_deleted_users_are_skipped_because_deleting_already_removed_their_image(): void
    {
        $user = $this->userWithAvatar('users/u1/0d1c2b3a.jpg', ImageFixtures::gif(2));
        $user->delete();

        $this->artisan('users:mark-animated-avatars')
            ->expectsOutputToContain('0 avatar(s) checked')
            ->assertSuccessful();

        $this->assertSame('users/u1/0d1c2b3a.jpg', UserModel::withTrashed()->findOrFail($user->id)->avatar_public_id);
    }

    // Safety branches: overlapping runs, an avatar changed meanwhile, disk failures

    public function test_a_second_run_at_the_same_time_stops_without_touching_anything(): void
    {
        $user = $this->userWithAvatar('users/u1/0d1c2b3a.jpg', ImageFixtures::gif(2));
        $command = $this->app->make(Kernel::class)->all()['users:mark-animated-avatars'];
        $mutex = $this->app->make(CacheCommandMutex::class);
        $this->assertTrue($mutex->create($command));

        try {
            $this->artisan('users:mark-animated-avatars')
                ->expectsOutputToContain('already running')
                ->assertFailed();
        } finally {
            $mutex->forget($command);
        }

        $this->assertSame('users/u1/0d1c2b3a.jpg', $user->refresh()->avatar_public_id);
        $this->disk->assertMissing('users/u1/0d1c2b3a-animated.gif');
    }

    public function test_a_copy_another_run_already_points_the_user_at_is_kept(): void
    {
        $gif = ImageFixtures::gif(2);
        $user = $this->userWithAvatar('users/u1/0d1c2b3a.jpg', $gif);
        // Another run finishes between this run's copy and its update
        $this->replaceDisk(afterPut: static function () use ($user): void {
            UserModel::query()->whereKey($user->id)->update(['avatar_public_id' => 'users/u1/0d1c2b3a-animated.gif']);
        });

        $this->artisan('users:mark-animated-avatars')
            ->expectsOutputToContain('already renamed by another run')
            ->expectsOutputToContain('0 failed')
            ->assertSuccessful();

        $this->assertSame('users/u1/0d1c2b3a-animated.gif', $user->refresh()->avatar_public_id);
        $this->assertSame($gif, $this->disk->get('users/u1/0d1c2b3a-animated.gif'));
    }

    public function test_a_copy_is_removed_when_the_user_changed_to_another_avatar_meanwhile(): void
    {
        $user = $this->userWithAvatar('users/u1/0d1c2b3a.jpg', ImageFixtures::gif(2));
        $this->disk->put('users/u1/fresh.png', ImageFixtures::png());
        $this->replaceDisk(afterPut: static function () use ($user): void {
            UserModel::query()->whereKey($user->id)->update(['avatar_public_id' => 'users/u1/fresh.png']);
        });

        $this->artisan('users:mark-animated-avatars')
            ->expectsOutputToContain('the avatar changed while renaming')
            ->expectsOutputToContain('1 failed')
            ->assertFailed();

        $this->assertSame('users/u1/fresh.png', $user->refresh()->avatar_public_id);
        $this->disk->assertMissing('users/u1/0d1c2b3a-animated.gif');
        $this->disk->assertExists(['users/u1/fresh.png', 'users/u1/0d1c2b3a.jpg']);
    }

    public function test_a_copy_the_disk_refuses_is_reported_and_the_user_left_alone(): void
    {
        $user = $this->userWithAvatar('users/u1/0d1c2b3a.jpg', ImageFixtures::gif(2));
        $this->replaceDisk(putResult: false);

        $this->artisan('users:mark-animated-avatars')
            ->expectsOutputToContain('users/u1/0d1c2b3a-animated.gif could not be stored (the disk refused it)')
            ->expectsOutputToContain('0 animated renamed, 0 already marked, 1 failed')
            ->assertFailed();

        $this->assertSame('users/u1/0d1c2b3a.jpg', $user->refresh()->avatar_public_id);
        $this->disk->assertExists('users/u1/0d1c2b3a.jpg');
    }

    public function test_a_copy_that_throws_is_reported_and_the_user_left_alone(): void
    {
        $user = $this->userWithAvatar('users/u1/0d1c2b3a.jpg', ImageFixtures::gif(2));
        $this->replaceDisk(afterPut: static function (): never {
            throw new RuntimeException('upload failed');
        });

        $this->artisan('users:mark-animated-avatars')
            ->expectsOutputToContain('could not be stored (RuntimeException)')
            ->assertFailed();

        $this->assertSame('users/u1/0d1c2b3a.jpg', $user->refresh()->avatar_public_id);
    }

    public function test_an_old_file_that_cannot_be_deleted_is_reported(): void
    {
        $user = $this->userWithAvatar('users/u1/0d1c2b3a.jpg', ImageFixtures::gif(2));
        $this->replaceDisk(deleteResult: false);

        $this->artisan('users:mark-animated-avatars')
            ->expectsOutputToContain('users/u1/0d1c2b3a.jpg could not be deleted (the disk refused it)')
            ->expectsOutputToContain('1 animated renamed, 0 already marked, 0 failed, 1 unused file(s) left behind')
            ->assertFailed();

        $this->assertSame('users/u1/0d1c2b3a-animated.gif', $user->refresh()->avatar_public_id);
        $this->disk->assertExists('users/u1/0d1c2b3a-animated.gif');
    }

    /**
     * Put a disk over the fake one that can refuse writes and deletes, or run code right after
     * a write (as another process would between this run's copy and its update).
     */
    private function replaceDisk(?Closure $afterPut = null, bool $putResult = true, bool $deleteResult = true): void
    {
        $fake = $this->disk;
        Storage::set('images', new class($fake, $afterPut, $putResult, $deleteResult) extends FilesystemAdapter
        {
            public function __construct(FilesystemAdapter $fake, private ?Closure $afterPut, private bool $putResult, private bool $deleteResult)
            {
                parent::__construct($fake->getDriver(), $fake->getAdapter(), $fake->getConfig());
            }

            public function put($path, $contents, $options = [])
            {
                if (! $this->putResult) {
                    return false;
                }

                $stored = parent::put($path, $contents, $options);

                if ($this->afterPut !== null) {
                    ($this->afterPut)();
                }

                return $stored;
            }

            public function delete($paths)
            {
                return $this->deleteResult ? parent::delete($paths) : false;
            }
        });
    }

    private function userWithAvatar(string $path, string $contents): UserModel
    {
        $this->disk->put($path, $contents);

        return UserModel::factory()->create(['avatar_public_id' => $path]);
    }
}
