<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Profiles;

use App\Application\Profiles\Services\UserProfileServiceInterface;
use App\Domain\Profiles\ProfileVisibility;
use App\Domain\Profiles\ValueObjects\ProfileHandle;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileHandleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The address of a public profile comes from the public name only, never from the account name, and an
 * address once used is never given to anybody else.
 */
final class UserProfileServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Six characters without look-alikes (no 0/o, 1/l/i) */
    private const string RANDOM = '[abcdefghjkmnpqrstuvwxyz23456789]{6}';

    private UserProfileServiceInterface $profiles;

    protected function setUp(): void
    {
        parent::setUp();

        $this->profiles = app(UserProfileServiceInterface::class);
    }

    public function test_a_new_account_gets_a_profile_with_the_defaults(): void
    {
        $user = UserModel::factory()->create(['name' => 'Ana García López', 'display_name' => 'Ana García']);

        $profile = $this->profileOf($user);
        $this->assertSame('ana-garcia', $profile->handle);
        $this->assertFalse($profile->handle_chosen);
        $this->assertSame(ProfileVisibility::Members, $profile->visibility);
        $this->assertNull($profile->bio);
        $this->assertNull($profile->banner_public_id);
        $this->assertFalse($profile->show_membership);
        $this->assertTrue($profile->layout->isEmpty());
    }

    public function test_without_a_public_name_the_address_is_random_and_never_the_account_name(): void
    {
        $user = UserModel::factory()->create(['name' => 'Ana García López', 'display_name' => null]);

        $handle = $this->profileOf($user)->handle;
        $this->assertMatchesRegularExpression('/^miembro-'.self::RANDOM.'$/', $handle);
        $this->assertStringNotContainsString('ana', $handle);
    }

    public function test_a_public_name_with_nothing_to_slug_gets_a_random_address(): void
    {
        $user = UserModel::factory()->create(['display_name' => '¡¿?!']);

        $this->assertMatchesRegularExpression('/^miembro-'.self::RANDOM.'$/', $this->profileOf($user)->handle);
    }

    public function test_a_long_public_name_is_cut_to_thirty_characters(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Bartolomé de las Casas y Mendoza del Río']);

        $this->assertSame('bartolome-de-las-casas-y-mendo', $this->profileOf($user)->handle);
    }

    public function test_a_cut_never_ends_with_a_hyphen(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Bartolomé de las Casas y Mend Ríos']);

        $this->assertSame('bartolome-de-las-casas-y-mend', $this->profileOf($user)->handle);
    }

    public function test_a_taken_address_gets_the_next_number(): void
    {
        $first = UserModel::factory()->create(['display_name' => 'Ana']);
        $second = UserModel::factory()->create(['display_name' => 'ANA']);
        $third = UserModel::factory()->create(['display_name' => 'Aná']);

        $this->assertSame('ana', $this->profileOf($first)->handle);
        $this->assertSame('ana-2', $this->profileOf($second)->handle);
        $this->assertSame('ana-3', $this->profileOf($third)->handle);
    }

    public function test_a_numbered_address_is_cut_so_it_still_fits(): void
    {
        $name = 'Bartolomé de las Casas y Mendoza del Río';
        UserModel::factory()->create(['display_name' => $name]);
        $second = UserModel::factory()->create(['display_name' => $name]);

        $this->assertSame('bartolome-de-las-casas-y-men-2', $this->profileOf($second)->handle);
    }

    public function test_an_address_used_before_is_never_given_to_someone_else(): void
    {
        $this->retire('ana');

        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $this->assertSame('ana-2', $this->profileOf($user)->handle);
    }

    public function test_an_anonymized_address_stays_reserved(): void
    {
        DB::table('user_profile_handles')->insert(['handle_hash' => ProfileHandle::hashOf('ana'), 'handle' => null, 'user_id' => null, 'retired_at' => now()]);

        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $this->assertSame('ana-2', $this->profileOf($user)->handle);
    }

    public function test_a_reserved_word_is_numbered(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Admin']);

        $this->assertSame('admin-2', $this->profileOf($user)->handle);
    }

    public function test_a_name_too_short_for_an_address_is_numbered(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Jo']);

        $this->assertSame('jo-2', $this->profileOf($user)->handle);
    }

    public function test_after_twenty_tries_the_address_gets_a_random_suffix(): void
    {
        $this->retire('ana');
        foreach (range(2, 20) as $number) {
            $this->retire('ana-'.$number);
        }

        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $this->assertMatchesRegularExpression('/^ana-'.self::RANDOM.'$/', $this->profileOf($user)->handle);
    }

    public function test_creating_twice_changes_nothing(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $this->assertFalse($this->profiles->createFor((string) $user->id));
        $this->assertSame(1, UserProfileModel::query()->count());
        $this->assertSame('ana', $this->profileOf($user)->handle);
    }

    public function test_an_anonymized_account_never_gets_a_profile(): void
    {
        $user = UserModel::factory()->create(['display_name' => null, 'anonymized_at' => now()]);
        UserProfileModel::query()->whereKey($user->id)->delete();

        $this->assertFalse($this->profiles->createFor((string) $user->id));
        $this->assertFalse($this->profiles->createFor('0198f0c6-0000-7000-8000-000000000000'));
        $this->assertSame(0, UserProfileModel::query()->count());
    }

    public function test_changing_the_public_name_moves_the_address_and_keeps_the_old_one_in_the_history(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $user->update(['display_name' => 'Bea Ruiz']);

        $this->assertSame('bea-ruiz', $this->profileOf($user)->handle);
        $history = UserProfileHandleModel::query()->findOrFail(ProfileHandle::hashOf('ana'));
        $this->assertSame('ana', $history->handle);
        $this->assertSame((string) $user->id, $history->user_id);
        $this->assertTrue($history->retired_at->lessThanOrEqualTo(now()));
    }

    public function test_setting_a_public_name_replaces_the_random_address(): void
    {
        $user = UserModel::factory()->create(['display_name' => null]);
        $random = $this->profileOf($user)->handle;

        $user->update(['display_name' => 'Ana']);

        $this->assertSame('ana', $this->profileOf($user)->handle);
        $this->assertTrue(UserProfileHandleModel::query()->whereKey(ProfileHandle::hashOf($random))->exists());
    }

    public function test_clearing_the_public_name_gives_a_random_address(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $user->update(['display_name' => null]);

        $this->assertMatchesRegularExpression('/^miembro-'.self::RANDOM.'$/', $this->profileOf($user)->handle);
    }

    public function test_a_random_address_stays_while_there_is_still_no_public_name(): void
    {
        $user = UserModel::factory()->create(['display_name' => null]);
        $random = $this->profileOf($user)->handle;

        $user->update(['display_name' => '']);

        $this->assertSame($random, $this->profileOf($user)->handle);
        $this->assertSame(0, UserProfileHandleModel::query()->count());
    }

    public function test_a_new_public_name_with_the_same_address_changes_nothing(): void
    {
        UserModel::factory()->create(['display_name' => 'Ana']);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $user->update(['display_name' => 'ANA']);

        $this->assertSame('ana-2', $this->profileOf($user)->handle);
        $this->assertSame(0, UserProfileHandleModel::query()->count());
    }

    public function test_a_chosen_address_no_longer_follows_the_public_name(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        UserProfileModel::query()->whereKey($user->id)->update(['handle' => 'la-ana', 'handle_chosen' => true]);

        $user->update(['display_name' => 'Bea']);

        $this->assertSame('la-ana', $this->profileOf($user)->handle);
        $this->assertSame(0, UserProfileHandleModel::query()->count());
    }

    public function test_the_owner_can_get_back_an_address_they_used_before(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $user->update(['display_name' => 'Bea']);

        $user->update(['display_name' => 'Ana']);

        $this->assertSame('ana', $this->profileOf($user)->handle);
        $this->assertFalse(UserProfileHandleModel::query()->whereKey(ProfileHandle::hashOf('ana'))->exists());
        $this->assertTrue(UserProfileHandleModel::query()->whereKey(ProfileHandle::hashOf('bea'))->exists());
    }

    public function test_an_account_name_change_leaves_the_address_alone(): void
    {
        $user = UserModel::factory()->create(['name' => 'Ana López', 'display_name' => 'Ana']);

        $user->update(['name' => 'Ana Ruiz']);

        $this->assertSame('ana', $this->profileOf($user)->handle);
    }

    public function test_following_the_public_name_of_an_account_without_profile_creates_nothing(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        UserProfileModel::query()->whereKey($user->id)->delete();

        $user->update(['display_name' => 'Bea']);

        $this->assertSame(0, UserProfileModel::query()->count());
    }

    public function test_forgetting_a_profile_keeps_only_the_fingerprints_of_its_addresses(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $user->update(['display_name' => 'Bea']);
        $other = UserModel::factory()->create(['display_name' => 'Carla']);
        $other->update(['display_name' => 'Dora']);

        $this->profiles->forget((string) $user->id);

        $this->assertNull(UserProfileModel::query()->find($user->id));
        $mine = UserProfileHandleModel::query()->whereIn('handle_hash', [ProfileHandle::hashOf('ana'), ProfileHandle::hashOf('bea')])->get();
        $this->assertCount(2, $mine);
        foreach ($mine as $row) {
            $this->assertNull($row->handle);
            $this->assertNull($row->user_id);
        }
        $theirs = UserProfileHandleModel::query()->findOrFail(ProfileHandle::hashOf('carla'));
        $this->assertSame('carla', $theirs->handle);
        $this->assertSame('dora', $this->profileOf($other)->handle);
    }

    public function test_creating_the_missing_profiles_creates_them_once(): void
    {
        $withProfile = UserModel::factory()->create(['display_name' => 'Ana']);
        $missing = UserModel::factory()->create(['display_name' => 'Bea']);
        $deactivated = UserModel::factory()->create(['display_name' => 'Carla']);
        $anonymized = UserModel::factory()->create(['display_name' => null, 'anonymized_at' => now()]);
        UserProfileModel::query()->whereKey([$missing->id, $deactivated->id, $anonymized->id])->delete();
        $deactivated->delete();

        $this->assertSame(2, $this->profiles->createMissing(dryRun: true, chunk: 1)->created);
        $this->assertSame(1, UserProfileModel::query()->count());

        $result = $this->profiles->createMissing(dryRun: false, chunk: 1);

        $this->assertSame(2, $result->created);
        $this->assertSame(0, $result->failed);
        $this->assertSame('ana', $this->profileOf($withProfile)->handle);
        $this->assertSame('bea', $this->profileOf($missing)->handle);
        $this->assertSame('carla', $this->profileOf($deactivated)->handle);
        $this->assertNull(UserProfileModel::query()->find($anonymized->id));
        $this->assertSame(0, $this->profiles->createMissing(dryRun: false, chunk: 500)->created);
    }

    private function profileOf(UserModel $user): UserProfileModel
    {
        return UserProfileModel::query()->findOrFail($user->id);
    }

    private function retire(string $handle): void
    {
        $owner = UserModel::factory()->create(['display_name' => null]);
        DB::table('user_profile_handles')->insert([
            'handle_hash' => ProfileHandle::hashOf($handle),
            'handle' => $handle,
            'user_id' => $owner->id,
            'retired_at' => now(),
        ]);
    }
}
