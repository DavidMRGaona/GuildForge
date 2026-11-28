<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Profiles;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Profiles\DTOs\VisibleProfileDTO;
use App\Application\Profiles\Services\VisibleProfilesInterface;
use App\Application\Services\EmailVerificationPolicyInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Profiles\Exceptions\VisibleProfilesUnavailableException;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\StoredProfiles;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * VisibleProfilesInterface::among() (core#840): which of many accounts' public profiles one person may
 * see, decided by the profile page's own rule in one call, with only what that page shows them; and
 * amongOrFail() (core#871), the same answer, except that a failed read throws instead of giving none.
 */
final class VisibleProfilesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_only_the_profiles_the_viewer_may_see_come_back(): void
    {
        $public = $this->owner('Ana', ProfileVisibility::Public);
        $members = $this->owner('Berta', ProfileVisibility::Members);
        $association = $this->owner('Carla', ProfileVisibility::Association);
        $hidden = $this->owner('Diana', ProfileVisibility::Hidden);
        $ids = [$public->id, $members->id, $association->id, $hidden->id];

        $this->assertSame([$public->id], $this->visible($ids, null));
        $this->assertSame([$public->id, $members->id], $this->visible($ids, UserModel::factory()->create()));
        // The owner sees their own, whatever the visibility (and, signed in, the members' ones)
        $this->assertSame([$public->id, $members->id, $hidden->id], $this->visible($ids, $hidden));
    }

    public function test_an_association_member_also_sees_the_association_profiles(): void
    {
        $association = $this->owner('Carla', ProfileVisibility::Association);
        $viewer = UserModel::factory()->create();
        $membership = Mockery::mock(AssociationMembershipServiceInterface::class);
        $membership->shouldReceive('isAssociationMember')->andReturnUsing(
            static fn (?Authenticatable $user): bool => $user instanceof Authenticatable && $user->getAuthIdentifier() === $viewer->id,
        );
        $this->app->instance(AssociationMembershipServiceInterface::class, $membership);

        $this->assertSame([$association->id], $this->visible([$association->id], $viewer));
        $this->assertSame([], $this->visible([$association->id], UserModel::factory()->create()));
    }

    public function test_a_member_who_must_still_verify_the_email_sees_only_the_public_ones(): void
    {
        $public = $this->owner('Ana', ProfileVisibility::Public);
        $members = $this->owner('Berta', ProfileVisibility::Members);
        $emails = Mockery::mock(EmailVerificationPolicyInterface::class);
        $emails->shouldReceive('mustVerify')->andReturnTrue();
        $this->app->instance(EmailVerificationPolicyInterface::class, $emails);

        $this->assertSame([$public->id], $this->visible([$public->id, $members->id], UserModel::factory()->create()));
    }

    public function test_each_profile_carries_its_address_public_name_and_avatar(): void
    {
        $owner = $this->owner('  Ana García ', ProfileVisibility::Public, 'avatars/ana.jpg');
        $handle = UserProfileModel::query()->findOrFail($owner->id)->handle;

        $this->assertEquals(
            [new VisibleProfileDTO($owner->id, $handle, 'Ana García', 'avatars/ana.jpg')],
            $this->among([$owner->id], null),
        );
    }

    public function test_the_profiles_come_in_the_order_asked_each_once(): void
    {
        $ana = $this->owner('Ana', ProfileVisibility::Public);
        $berta = $this->owner('Berta', ProfileVisibility::Public);
        $carla = $this->owner('Carla', ProfileVisibility::Public);

        $this->assertSame(
            [$carla->id, $ana->id, $berta->id],
            $this->visible([$carla->id, $ana->id, $carla->id, $berta->id, $ana->id], null),
        );
    }

    public function test_ids_are_read_in_lower_case_and_anything_that_is_not_one_is_ignored(): void
    {
        $owner = $this->owner('Ana', ProfileVisibility::Public);

        $this->assertSame([$owner->id], $this->visible([strtoupper($owner->id), 'no es un id', '', $owner->id], null));
    }

    public function test_hidden_deactivated_anonymized_and_rowless_accounts_never_come_back(): void
    {
        $hidden = $this->owner('Ana', ProfileVisibility::Hidden);
        $deactivated = $this->owner('Berta', ProfileVisibility::Public);
        $deactivated->delete();
        $anonymized = $this->owner('Carla', ProfileVisibility::Public);
        app(UserServiceInterface::class)->anonymize((string) $anonymized->id);
        $rowless = $this->owner('Diana', ProfileVisibility::Public);
        UserProfileModel::query()->whereKey($rowless->id)->delete();
        $ids = ['019a0000-0000-7000-8000-000000000000', $hidden->id, $deactivated->id, $anonymized->id, $rowless->id];

        $this->assertSame([], $this->visible($ids, null));
        $this->assertSame([], $this->visible($ids, UserModel::factory()->create()));
        // Not even to themselves: a deactivated account has no public profile
        $this->assertSame([], $this->visible($ids, $deactivated));
    }

    public function test_without_a_public_name_only_the_owner_sees_it_and_without_a_name(): void
    {
        $nameless = $this->owner('Ana', ProfileVisibility::Public);
        $nameless->forceFill(['display_name' => null])->save();
        $blank = $this->owner('Berta', ProfileVisibility::Public);
        $blank->forceFill(['display_name' => '   '])->save();
        $ids = [$nameless->id, $blank->id];

        $this->assertSame([], $this->visible($ids, null));
        $this->assertSame([], $this->visible($ids, UserModel::factory()->create()));

        $own = $this->among($ids, $nameless);
        $this->assertCount(1, $own);
        $this->assertSame($nameless->id, $own[0]->userId);
        $this->assertNull($own[0]->publicName);
    }

    public function test_it_never_gives_the_account_name_the_email_or_the_roles(): void
    {
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $owner->forceFill(['name' => 'nombre-de-cuenta-privado', 'email' => 'privado@example.com'])->save();

        $answer = (string) json_encode(array_map(get_object_vars(...), $this->among([$owner->id], null)));

        $this->assertStringNotContainsString('nombre-de-cuenta-privado', $answer);
        $this->assertStringNotContainsString('privado@example.com', $answer);
        $this->assertStringNotContainsString('role', $answer);
    }

    public function test_a_call_costs_the_same_queries_whatever_the_number_of_accounts(): void
    {
        $few = [$this->owner('Ana', ProfileVisibility::Public)->id, $this->owner('Berta', ProfileVisibility::Members)->id];
        $many = [...$few];
        foreach (['Carla', 'Diana', 'Elena', 'Fátima', 'Gema', 'Helena'] as $name) {
            $many[] = $this->owner($name, ProfileVisibility::Members)->id;
        }
        $viewer = UserModel::factory()->create();

        $this->assertSame($this->queriesFor($few, $viewer), $this->queriesFor($many, $viewer));
        $this->assertCount(8, $this->among($many, $viewer));
    }

    public function test_nothing_is_read_without_any_account_id(): void
    {
        $this->assertSame(0, $this->queriesFor(['no es un id'], null));
        $this->assertSame([], $this->among([], null));
    }

    public function test_more_than_the_limit_is_refused_before_reading_anything(): void
    {
        $ids = [];
        for ($i = 0; $i <= VisibleProfilesInterface::MAX_IDS; $i++) {
            $ids[] = (string) Str::uuid7();
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        try {
            $this->among($ids, null);
            $this->fail('More than MAX_IDS accounts were accepted.');
        } catch (InvalidArgumentException) {
            $this->assertSame([], DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }
    }

    public function test_the_limit_counts_each_account_once(): void
    {
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $ids = array_fill(0, VisibleProfilesInterface::MAX_IDS + 1, $owner->id);
        $ids[] = 'no es un id';

        $this->assertSame([$owner->id], $this->visible($ids, null));
    }

    public function test_a_failing_access_check_hides_the_profiles_that_need_it(): void
    {
        $public = $this->owner('Ana', ProfileVisibility::Public);
        $association = $this->owner('Berta', ProfileVisibility::Association);
        $membership = Mockery::mock(AssociationMembershipServiceInterface::class);
        $membership->shouldReceive('isAssociationMember')->andThrow(new RuntimeException('roles unreadable'));
        $this->app->instance(AssociationMembershipServiceInterface::class, $membership);

        $this->assertSame([$public->id], $this->visible([$public->id, $association->id], UserModel::factory()->create()));
    }

    public function test_any_other_failure_gives_nothing_and_a_warning(): void
    {
        $log = Log::spy();
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $this->app->bind(StoredProfiles::class, static fn (): never => throw new RuntimeException('ana@example.com is gone'));

        $this->assertSame([], $this->app->make(VisibleProfilesInterface::class)->among([$owner->id], null));
        $log->shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => $context === ['exception' => RuntimeException::class],
        );
    }

    public function test_a_stored_row_the_page_would_not_answer_fails_closed(): void
    {
        $log = Log::spy();
        $broken = $this->owner('Ana', ProfileVisibility::Public);
        $other = $this->owner('Berta', ProfileVisibility::Public);
        // Written around the profile service, which would never store it
        DB::table('user_profiles')->where('user_id', $broken->id)->update(['handle' => 'Not A Handle']);

        $this->assertSame([], $this->visible([$other->id, $broken->id], null));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_the_strict_read_answers_what_among_answers(): void
    {
        $public = $this->owner('Ana', ProfileVisibility::Public);
        $members = $this->owner('Berta', ProfileVisibility::Members);
        $hidden = $this->owner('Diana', ProfileVisibility::Hidden);
        $ids = [$hidden->id, strtoupper($members->id), $public->id, 'no es un id', $public->id];
        $viewer = UserModel::factory()->create();

        $this->assertEquals($this->among($ids, $viewer), $this->strictly($ids, $viewer));
        $this->assertSame([$members->id, $public->id], array_map(
            static fn (VisibleProfileDTO $profile): string => $profile->userId,
            $this->strictly($ids, $viewer),
        ));
    }

    public function test_the_strict_read_gives_an_empty_list_only_when_nobody_is_visible(): void
    {
        $hidden = $this->owner('Diana', ProfileVisibility::Hidden);
        $withoutRow = UserModel::factory()->create();
        UserProfileModel::query()->whereKey($withoutRow->id)->delete();

        $this->assertSame([], $this->strictly([], null));
        $this->assertSame([], $this->strictly(['no es un id'], null));
        $this->assertSame([], $this->strictly([$hidden->id, $withoutRow->id], null));
    }

    public function test_the_strict_read_throws_when_the_profiles_cannot_be_read(): void
    {
        $log = Log::spy();
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $this->app->bind(StoredProfiles::class, static fn (): never => throw new RuntimeException('ana@example.com is gone'));

        try {
            $this->app->make(VisibleProfilesInterface::class)->amongOrFail([$owner->id], null);
            $this->fail('A failed read must not answer a list.');
        } catch (VisibleProfilesUnavailableException $exception) {
            // Neither the cause nor anything it says reaches whoever catches it
            $this->assertNull($exception->getPrevious());
            $this->assertStringNotContainsString('ana@example.com', $exception->getMessage());
            $this->assertStringNotContainsString($owner->id, $exception->getMessage());
        }

        $log->shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => $context === ['exception' => RuntimeException::class],
        );
    }

    public function test_the_strict_read_throws_on_a_stored_row_the_page_would_not_answer(): void
    {
        $broken = $this->owner('Ana', ProfileVisibility::Public);
        $other = $this->owner('Berta', ProfileVisibility::Public);
        DB::table('user_profiles')->where('user_id', $broken->id)->update(['handle' => 'Not A Handle']);

        $this->expectException(VisibleProfilesUnavailableException::class);

        $this->strictly([$other->id, $broken->id], null);
    }

    public function test_the_strict_read_keeps_the_access_rule_failing_closed_per_profile(): void
    {
        // The access rule decides alike in both reads: a membership check it cannot make hides the
        // profiles that need it and leaves the others, as the profile page does; the read itself worked
        $public = $this->owner('Ana', ProfileVisibility::Public);
        $association = $this->owner('Berta', ProfileVisibility::Association);
        $membership = Mockery::mock(AssociationMembershipServiceInterface::class);
        $membership->shouldReceive('isAssociationMember')->andThrow(new RuntimeException('roles unreadable'));
        $this->app->instance(AssociationMembershipServiceInterface::class, $membership);

        $profiles = $this->strictly([$public->id, $association->id], UserModel::factory()->create());

        $this->assertSame([$public->id], array_map(static fn (VisibleProfileDTO $profile): string => $profile->userId, $profiles));
    }

    public function test_the_strict_read_refuses_more_than_the_limit_before_reading(): void
    {
        $ids = [];
        for ($i = 0; $i <= VisibleProfilesInterface::MAX_IDS; $i++) {
            $ids[] = sprintf('0195f3a2-7c1e-7d4b-9a8e-%012x', $i);
        }
        $this->app->bind(StoredProfiles::class, static fn (): never => throw new RuntimeException('read'));

        $this->expectException(InvalidArgumentException::class);

        $this->strictly($ids, null);
    }

    private function owner(string $name, ProfileVisibility $visibility, ?string $avatar = null): UserModel
    {
        $owner = UserModel::factory()->create(['display_name' => $name, 'avatar_public_id' => $avatar]);
        UserProfileModel::query()->whereKey($owner->id)->update(['visibility' => $visibility->value]);

        return $owner;
    }

    /**
     * One request: what the request remembered is forgotten first.
     *
     * @param  list<string>  $ids
     * @return list<VisibleProfileDTO>
     */
    private function among(array $ids, ?Authenticatable $viewer): array
    {
        $this->app->forgetScopedInstances();

        return $this->app->make(VisibleProfilesInterface::class)->among($ids, $viewer);
    }

    /**
     * @param  list<string>  $ids
     * @return list<VisibleProfileDTO>
     */
    private function strictly(array $ids, ?Authenticatable $viewer): array
    {
        $this->app->forgetScopedInstances();

        return $this->app->make(VisibleProfilesInterface::class)->amongOrFail($ids, $viewer);
    }

    /**
     * @param  list<string>  $ids
     * @return list<string>
     */
    private function visible(array $ids, ?Authenticatable $viewer): array
    {
        return array_map(static fn (VisibleProfileDTO $profile): string => $profile->userId, $this->among($ids, $viewer));
    }

    /**
     * @param  list<string>  $ids
     */
    private function queriesFor(array $ids, ?Authenticatable $viewer): int
    {
        // Warm what any request has read already (settings, roles), so only the call's own reads count
        $this->among($ids, $viewer);
        $this->app->forgetScopedInstances();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->app->make(VisibleProfilesInterface::class)->among($ids, $viewer);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }
}
