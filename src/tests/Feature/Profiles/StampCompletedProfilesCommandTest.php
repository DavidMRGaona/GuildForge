<?php

declare(strict_types=1);

namespace Tests\Feature\Profiles;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Activity\FakesActivityEvents;
use Tests\TestCase;

/**
 * `profiles:stamp-completed` (core#777): existing sites stamp the profiles that were complete before
 * the date existed, once, after deploying.
 */
final class StampCompletedProfilesCommandTest extends TestCase
{
    use FakesActivityEvents;
    use LazilyRefreshDatabase;

    private function completeButUnstamped(string $updatedAt = '2026-02-03 10:00:00'): UserModel
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana', 'avatar_public_id' => 'avatars/ana.png']);
        UserProfileModel::query()->whereKey($user->id)->toBase()->update(['bio' => 'Hola.', 'completed_at' => null, 'updated_at' => $updatedAt]);

        return $user;
    }

    private function completedAt(UserModel $user): ?string
    {
        return UserProfileModel::query()->findOrFail($user->id)->completed_at?->format('Y-m-d H:i:s');
    }

    public function test_it_stamps_a_complete_profile_with_its_last_change_and_publishes_it(): void
    {
        $user = $this->completeButUnstamped('2026-02-03 10:00:00');
        $this->fakeActivityEvents();

        $this->artisanCommand('profiles:stamp-completed')->assertSuccessful();

        $this->assertSame('2026-02-03 10:00:00', $this->completedAt($user));
        $this->assertActivityPublished('core.profile-completed', (string) $user->id);
    }

    public function test_it_leaves_incomplete_stamped_and_anonymized_profiles_alone(): void
    {
        $withoutAvatar = UserModel::factory()->create(['display_name' => 'Ana', 'avatar_public_id' => null]);
        UserProfileModel::query()->whereKey($withoutAvatar->id)->toBase()->update(['bio' => 'Hola.']);
        $stamped = $this->completeButUnstamped();
        UserProfileModel::query()->whereKey($stamped->id)->toBase()->update(['completed_at' => '2026-01-01 00:00:00']);
        $blank = UserModel::factory()->create(['display_name' => 'Ana', 'avatar_public_id' => 'avatars/a.png']);
        UserProfileModel::query()->whereKey($blank->id)->toBase()->update(['bio' => " \n\t "]);
        $anonymized = $this->completeButUnstamped();
        UserModel::query()->whereKey($anonymized->id)->toBase()->update(['anonymized_at' => now(), 'display_name' => null]);
        $this->fakeActivityEvents();

        $this->artisanCommand('profiles:stamp-completed')->assertSuccessful();

        $this->assertNull($this->completedAt($withoutAvatar));
        $this->assertNull($this->completedAt($blank));
        $this->assertNull($this->completedAt($anonymized));
        $this->assertSame('2026-01-01 00:00:00', $this->completedAt($stamped));
        $this->assertNoActivityPublished();
    }

    public function test_a_dry_run_writes_and_publishes_nothing(): void
    {
        $user = $this->completeButUnstamped();
        $this->fakeActivityEvents();

        $this->artisanCommand('profiles:stamp-completed', ['--dry-run' => true])
            ->expectsOutputToContain('1')
            ->assertSuccessful();

        $this->assertNull($this->completedAt($user));
        $this->assertNoActivityPublished();
    }

    public function test_it_is_idempotent_and_works_in_small_batches(): void
    {
        $users = [$this->completeButUnstamped(), $this->completeButUnstamped(), $this->completeButUnstamped()];

        $this->artisanCommand('profiles:stamp-completed', ['--chunk' => 2])->assertSuccessful();
        $first = array_map($this->completedAt(...), $users);

        $this->fakeActivityEvents();
        $this->artisanCommand('profiles:stamp-completed', ['--chunk' => 2])->assertSuccessful();

        $this->assertNotContains(null, $first);
        $this->assertSame($first, array_map($this->completedAt(...), $users));
        $this->assertNoActivityPublished();
    }
}
