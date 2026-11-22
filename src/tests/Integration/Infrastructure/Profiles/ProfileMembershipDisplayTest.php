<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Profiles;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Authorization\Services\AuthorizationServiceInterface;
use App\Application\Profiles\Services\ProfileMembershipDisplayInterface;
use App\Application\Services\SettingsServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\TestCase;

/**
 * ProfileMembershipDisplayInterface::showsMembership() (core#824): whether the «Socio» badge of an account
 * is shown to the members, in one call, the same for everybody and failing closed.
 */
final class ProfileMembershipDisplayTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Each test warns afresh: the marks live in the default cache for a day
        Cache::flush();
    }

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

    public function test_it_never_throws_and_fails_closed_with_one_warning_a_day(): void
    {
        $member = $this->shower();
        $other = $this->shower();
        $membership = Mockery::mock(AssociationMembershipServiceInterface::class);
        $membership->shouldReceive('roleName')->andThrow(new RuntimeException('secret@example.com'));
        $this->app->instance(AssociationMembershipServiceInterface::class, $membership);
        $log = Log::spy();

        $first = $this->shows([$member->id, $other->id]);
        $second = $this->shows([$member->id]);

        $this->assertSame([$member->id => false, $other->id => false], $first);
        $this->assertSame([$member->id => false], $second);
        $log->shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => ($context['exception'] ?? null) === RuntimeException::class
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'secret@example.com'),
        );
    }

    public function test_more_accounts_than_the_limit_answer_false_beyond_it(): void
    {
        $member = $this->shower();
        $ids = [];
        for ($i = 0; $i < ProfileMembershipDisplayInterface::MAX_IDS; $i++) {
            $ids[] = fake()->uuid();
        }
        $ids[] = $member->id;
        $log = Log::spy();

        $answer = $this->shows($ids);

        $this->assertCount(ProfileMembershipDisplayInterface::MAX_IDS + 1, $answer);
        $this->assertFalse($answer[$member->id]);
        $log->shouldHaveReceived('warning')->once();
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
