<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Profiles;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\Services\ProfileMembershipDisplayInterface;
use App\Application\Profiles\Services\ProfileRendererInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use App\Domain\Profiles\Exceptions\ProfileMembershipDisplayUnavailableException;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * ProfileMembershipDisplayInterface::showsMembership() (core#824): whether the «Socio» badge of an account
 * is shown to the members, in one call and the same for everybody. A failure to read throws: «false» is only an answer.
 */
final class ProfileMembershipDisplayTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_member_who_shows_the_badge_to_everybody_or_to_members_is_true(): void
    {
        $public = $this->shower(ProfileVisibility::Public);
        $members = $this->shower(ProfileVisibility::Members);

        $this->assertSame(
            [$public->id => true, $members->id => true],
            $this->shows([$public->id, $members->id]),
        );
    }

    public function test_every_condition_is_needed(): void
    {
        $noProfile = $this->shower();
        UserProfileModel::query()->whereKey($noProfile->id)->delete();

        $deactivated = $this->shower();
        $deactivated->delete();

        $anonymized = $this->shower();
        app(UserServiceInterface::class)->anonymize((string) $anonymized->id);

        $noName = $this->shower();
        $noName->forceFill(['display_name' => null])->save();

        $blankName = $this->shower();
        $blankName->forceFill(['display_name' => '   '])->save();

        $badgeOff = $this->shower();
        UserProfileModel::query()->whereKey($badgeOff->id)->update(['show_membership' => false]);

        $association = $this->shower(ProfileVisibility::Association);
        $hidden = $this->shower(ProfileVisibility::Hidden);

        $notMember = $this->shower();
        app(AuthorizationServiceInterface::class)->removeRole($notMember, 'socio');

        $unknownVisibility = $this->shower();
        DB::table('user_profiles')->where('user_id', $unknownVisibility->id)->update(['visibility' => 'something-else']);

        $answer = $this->shows(array_map(
            static fn (UserModel $user): string => (string) $user->id,
            [$noProfile, $deactivated, $anonymized, $noName, $blankName, $badgeOff, $association, $hidden, $notMember, $unknownVisibility],
        ));

        $this->assertCount(10, $answer);
        $this->assertSame([false], array_values(array_unique($answer)));
    }

    public function test_the_answer_follows_the_association_role_setting(): void
    {
        $member = $this->shower();
        $junta = RoleModel::query()->create(['name' => 'junta', 'display_name' => 'Junta']);

        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'junta');
        $this->assertSame([$member->id => false], $this->shows([$member->id]));

        $member->roles()->attach($junta->id);
        $this->assertSame([$member->id => true], $this->shows([$member->id]));

        // A forbidden value means nobody is a member
        app(SettingsServiceInterface::class)->set(AssociationMembershipServiceInterface::SETTING_KEY, 'admin');
        $this->assertSame([$member->id => false], $this->shows([$member->id]));
    }

    public function test_a_role_that_needs_two_factor_gives_nothing_without_it(): void
    {
        $member = $this->shower();
        RoleModel::query()->where('name', 'socio')->update(['requires_two_factor' => true]);

        $this->assertSame([$member->id => false], $this->shows([$member->id]));
    }

    public function test_the_answer_is_keyed_by_lower_case_id_and_ignores_what_is_not_an_id(): void
    {
        $member = $this->shower();
        $upper = strtoupper((string) $member->id);

        $this->assertSame([$member->id => true], $this->shows([$upper, $member->id, 'not-an-id', '']));
        $this->assertSame([], $this->shows([]));
        $this->assertSame([], $this->shows(['not-an-id']));
    }

    public function test_it_is_the_same_for_every_viewer(): void
    {
        $member = $this->shower(ProfileVisibility::Members);
        $outsider = UserModel::factory()->create();

        $anonymous = $this->shows([$member->id]);
        $this->actingAs($outsider);
        $asOutsider = $this->shows([$member->id]);
        $this->actingAs($member);
        $asOwner = $this->shows([$member->id]);

        $this->assertSame([$member->id => true], $anonymous);
        $this->assertSame($anonymous, $asOutsider);
        $this->assertSame($anonymous, $asOwner);
    }

    public function test_a_call_costs_the_same_queries_whatever_the_number_of_accounts(): void
    {
        $few = [$this->shower()->id, $this->shower(ProfileVisibility::Hidden)->id];
        $many = [...$few];
        for ($i = 0; $i < 8; $i++) {
            $many[] = $this->shower()->id;
        }
        $this->shows($few);

        DB::enableQueryLog();
        DB::flushQueryLog();
        $this->shows($few);
        $queriesForFew = count(DB::getQueryLog());
        DB::flushQueryLog();
        $answer = $this->shows($many);
        $queriesForMany = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(10, $answer);
        $this->assertGreaterThan(0, $queriesForFew);
        $this->assertSame($queriesForFew, $queriesForMany);
        $this->assertLessThanOrEqual(3, $queriesForMany);
    }

    public function test_a_failure_to_read_throws_instead_of_answering_false(): void
    {
        $member = $this->shower();
        $membership = Mockery::mock(AssociationMembershipServiceInterface::class);
        $membership->shouldReceive('roleName')->andThrow(new RuntimeException('secret@example.com'));
        $this->app->instance(AssociationMembershipServiceInterface::class, $membership);

        try {
            $this->shows([$member->id]);
            $this->fail('A failure to read must not look like «does not show it».');
        } catch (ProfileMembershipDisplayUnavailableException $exception) {
            // Nothing a person can see travels in it, nor the cause
            $this->assertStringNotContainsString('secret@example.com', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
        }
    }

    public function test_a_two_factor_reader_that_fails_throws_when_the_role_needs_it(): void
    {
        $member = $this->shower();
        RoleModel::query()->where('name', 'socio')->update(['requires_two_factor' => true]);
        $reader = Mockery::mock(TwoFactorStatusReaderInterface::class);
        $reader->shouldReceive('enabledAmong')->andThrow(new RuntimeException('broken'));
        $this->app->instance(TwoFactorStatusReaderInterface::class, $reader);

        $this->expectException(ProfileMembershipDisplayUnavailableException::class);

        $this->shows([$member->id]);
    }

    public function test_a_two_factor_reader_that_fails_is_not_asked_when_the_role_does_not_need_it(): void
    {
        $member = $this->shower();
        $reader = Mockery::mock(TwoFactorStatusReaderInterface::class);
        $reader->shouldReceive('enabledAmong')->never();
        $this->app->instance(TwoFactorStatusReaderInterface::class, $reader);

        $this->assertSame([$member->id => true], $this->shows([$member->id]));
    }

    public function test_another_role_that_needs_two_factor_does_not_make_the_reader_ask_for_it(): void
    {
        $member = $this->shower();
        $council = RoleModel::query()->create(['name' => 'consejo', 'display_name' => 'Consejo', 'requires_two_factor' => true]);
        $member->roles()->attach($council->id);
        // The answer depends on the association role alone, so a reader that fails is never asked
        $reader = Mockery::mock(TwoFactorStatusReaderInterface::class);
        $reader->shouldReceive('enabledAmong')->never();
        $reader->shouldReceive('isEnabled')->never();
        $this->app->instance(TwoFactorStatusReaderInterface::class, $reader);

        $this->assertSame([$member->id => true], $this->shows([$member->id]));
    }

    public function test_more_accounts_than_the_limit_are_refused_not_cut(): void
    {
        $ids = [];
        for ($i = 0; $i <= ProfileMembershipDisplayInterface::MAX_IDS; $i++) {
            $ids[] = fake()->uuid();
        }

        $this->expectException(InvalidArgumentException::class);

        $this->shows($ids);
    }

    public function test_exactly_the_limit_is_answered(): void
    {
        $ids = [];
        for ($i = 0; $i < ProfileMembershipDisplayInterface::MAX_IDS; $i++) {
            $ids[] = fake()->uuid();
        }

        $this->assertCount(ProfileMembershipDisplayInterface::MAX_IDS, $this->shows($ids));
    }

    public function test_the_reader_and_the_badge_of_the_profile_page_agree(): void
    {
        $accounts = [];
        foreach ([ProfileVisibility::Public, ProfileVisibility::Members] as $visibility) {
            $accounts[] = $this->shower($visibility);
            $notMember = $this->shower($visibility);
            app(AuthorizationServiceInterface::class)->removeRole($notMember, 'socio');
            $accounts[] = $notMember;
            $badgeOff = $this->shower($visibility);
            UserProfileModel::query()->whereKey($badgeOff->id)->update(['show_membership' => false]);
            $accounts[] = $badgeOff;
        }
        $ids = array_map(static fn (UserModel $user): string => (string) $user->id, $accounts);

        foreach ([false, true] as $requiresTwoFactor) {
            RoleModel::query()->where('name', 'socio')->update(['requires_two_factor' => $requiresTwoFactor]);
            // Two-factor on for one account only
            $reader = Mockery::mock(TwoFactorStatusReaderInterface::class);
            $reader->shouldReceive('enabledAmong')->andReturn([$ids[0]]);
            $reader->shouldReceive('isEnabled')->andReturnUsing(static fn (string $id): bool => $id === $ids[0]);
            $this->app->instance(TwoFactorStatusReaderInterface::class, $reader);
            app(AuthorizationServiceInterface::class)->invalidateCache();

            $answer = $this->shows($ids);
            $onThePage = [];
            foreach ($accounts as $account) {
                $this->app->forgetScopedInstances();
                $profile = UserProfileModel::query()->findOrFail($account->id);
                $header = $this->app->make(ProfileRendererInterface::class)
                    ->render(new ProfileOwnerDTO((string) $account->id, $profile->handle, $account->display_name), null)->header;
                // The page also needs the profile to be visible to this viewer: the reader asks for members
                $onThePage[(string) $account->id] = $header->showsMembership;
            }

            $this->assertSame($onThePage, $answer, 'two-factor requirement: '.var_export($requiresTwoFactor, true));
        }
    }

    /**
     * A member whose profile shows the badge, with a public name.
     */
    private function shower(ProfileVisibility $visibility = ProfileVisibility::Members): UserModel
    {
        $user = UserModel::factory()->create(['display_name' => fake()->unique()->userName()]);
        app(AuthorizationServiceInterface::class)->assignRole($user, 'socio');
        UserProfileModel::query()->whereKey($user->id)->update([
            'visibility' => $visibility->value,
            'show_membership' => true,
        ]);

        return $user;
    }

    /**
     * One call, as a job would make it: what the container remembered is forgotten first.
     *
     * @param  list<string>  $ids
     * @return array<string, bool>
     */
    private function shows(array $ids): array
    {
        $this->app->forgetScopedInstances();

        return $this->app->make(ProfileMembershipDisplayInterface::class)->showsMembership($ids);
    }
}
