<?php

declare(strict_types=1);

namespace Tests\Feature\Console\Commands;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Storage;
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

    private Filesystem $disk;

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

    private function userWithAvatar(string $path, string $contents): UserModel
    {
        $this->disk->put($path, $contents);

        return UserModel::factory()->create(['avatar_public_id' => $path]);
    }
}
