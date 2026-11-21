<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Profiles;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Profiles\DTOs\ProfileBadgeDTO;
use App\Application\Profiles\Services\ProfileBadgesInterface;
use App\Application\Profiles\Services\ProfileBadgeSourceRegistryInterface;
use App\Application\Services\UserServiceInterface;
use App\Domain\Profiles\ProfileBadgeColour;
use App\Domain\Profiles\ProfileVisibility;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\ProfileContributionRegistry;
use App\Infrastructure\Profiles\StoredProfiles;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use stdClass;
use Tests\Support\Profiles\FakeProfileBadgeSource;
use Tests\TestCase;

/**
 * ProfileBadgesInterface::forAuthors() (core#776): the featured badges of a page's authors for one
 * person, in one call, only for the profiles that person may see, failing closed per source.
 */
final class ProfileBadgesTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $bindings = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Each test warns afresh: the marks live in the default cache for a day
        Cache::flush();
    }

    public function test_badges_come_only_for_the_authors_the_viewer_may_see(): void
    {
        $public = $this->owner('Ana', ProfileVisibility::Public);
        $members = $this->owner('Berta', ProfileVisibility::Members);
        $hidden = $this->owner('Carla', ProfileVisibility::Hidden);
        $this->source(new FakeProfileBadgeSource);
        $ids = [$public->id, $members->id, $hidden->id];

        $this->assertSame([$public->id], array_keys($this->forAuthors($ids, null)));
        $this->assertSame([$public->id, $members->id], array_keys($this->forAuthors($ids, UserModel::factory()->create())));
        // The owner sees their own, whatever the visibility (and, signed in, the members' ones)
        $this->assertSame($ids, array_keys($this->forAuthors($ids, $hidden)));
    }

    public function test_the_badges_are_what_the_source_gave(): void
    {
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $badge = FakeProfileBadgeSource::badge(
            'achievements.veteran',
            label: 'Veterana',
            description: 'Cinco años en la asociación.',
            icon: 'medal',
            colour: ProfileBadgeColour::Violet,
            imageUrl: 'https://res.cloudinary.com/demo/image/upload/achievements/badges/veteran.png',
            url: '/logros/veterana',
        );
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [$ids[0] => [$badge]]));

        $badges = $this->forAuthors([$owner->id], null);

        $this->assertEquals([$owner->id => [$badge]], $badges);
        $this->assertSame([
            'key' => 'achievements.veteran',
            'label' => 'Veterana',
            'description' => 'Cinco años en la asociación.',
            'icon' => 'medal',
            'colour' => 'violet',
            'imageUrl' => 'https://res.cloudinary.com/demo/image/upload/achievements/badges/veteran.png',
            'url' => '/logros/veterana',
        ], $badges[$owner->id][0]->toArray());
    }

    public function test_each_source_is_asked_once_and_only_about_authors_the_viewer_may_see(): void
    {
        $public = $this->owner('Ana', ProfileVisibility::Public);
        $other = $this->owner('Berta', ProfileVisibility::Public);
        $hidden = $this->owner('Carla', ProfileVisibility::Hidden);
        $first = new FakeProfileBadgeSource;
        $second = new FakeProfileBadgeSource;
        $this->source($first);
        $this->source($second);

        $this->forAuthors([$public->id, $hidden->id, $other->id, $public->id], null);

        $this->assertSame([[$public->id, $other->id]], $first->calls);
        $this->assertSame([[$public->id, $other->id]], $second->calls);
    }

    public function test_a_page_costs_the_same_queries_whatever_the_number_of_authors(): void
    {
        $this->source(new FakeProfileBadgeSource);
        $few = [$this->owner('Ana', ProfileVisibility::Public)->id, $this->owner('Berta', ProfileVisibility::Members)->id];
        $many = [...$few];
        foreach (['Carla', 'Diana', 'Elena', 'Fátima', 'Gema', 'Helena'] as $name) {
            $many[] = $this->owner($name, ProfileVisibility::Members)->id;
        }
        $viewer = UserModel::factory()->create();

        $this->assertSame($this->queriesFor($few, $viewer), $this->queriesFor($many, $viewer));
        $this->assertCount(8, $this->forAuthors($many, $viewer));
    }

    public function test_without_any_source_nothing_is_read(): void
    {
        $owner = $this->owner('Ana', ProfileVisibility::Public);

        $this->assertSame(0, $this->queriesFor([$owner->id], null));
        $this->assertSame([], $this->forAuthors([$owner->id], null));
    }

    public function test_ids_are_read_in_lower_case_and_anything_that_is_not_one_is_ignored(): void
    {
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $source = new FakeProfileBadgeSource;
        $this->source($source);

        $badges = $this->forAuthors([strtoupper($owner->id), 'no es un id', '', $owner->id], null);

        $this->assertSame([$owner->id], array_keys($badges));
        $this->assertSame([[$owner->id]], $source->calls);
    }

    public function test_no_badges_for_unknown_deactivated_anonymized_or_rowless_accounts(): void
    {
        $deactivated = $this->owner('Ana', ProfileVisibility::Public);
        $deactivated->delete();
        $anonymized = $this->owner('Berta', ProfileVisibility::Public);
        app(UserServiceInterface::class)->anonymize((string) $anonymized->id);
        $rowless = $this->owner('Carla', ProfileVisibility::Public);
        UserProfileModel::query()->whereKey($rowless->id)->delete();
        $source = new FakeProfileBadgeSource;
        $this->source($source);

        $ids = ['019a0000-0000-7000-8000-000000000000', $deactivated->id, $anonymized->id, $rowless->id];

        $this->assertSame([], $this->forAuthors($ids, null));
        $this->assertSame([], $this->forAuthors($ids, $deactivated));
        $this->assertSame([], $source->calls);
    }

    public function test_at_most_three_badges_per_account_in_the_order_of_the_sources(): void
    {
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [$ids[0] => [
            FakeProfileBadgeSource::badge('achievements.one'),
            FakeProfileBadgeSource::badge('achievements.two'),
        ]]));
        $this->source(new FakeProfileBadgeSource('tournaments', static fn (array $ids): array => [$ids[0] => [
            FakeProfileBadgeSource::badge('tournaments.podium'),
            FakeProfileBadgeSource::badge('tournaments.winner'),
        ]]), 'tournaments');

        $this->assertSame(
            ['achievements.one', 'achievements.two', 'tournaments.podium'],
            $this->keys($this->forAuthors([$owner->id], null)[$owner->id]),
        );
    }

    public function test_a_source_giving_more_than_three_is_cut_and_warned_about(): void
    {
        $log = Log::spy();
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [$ids[0] => array_map(
            static fn (string $name): ProfileBadgeDTO => FakeProfileBadgeSource::badge('achievements.'.$name),
            ['one', 'two', 'three', 'four'],
        )]));

        $this->assertSame(['achievements.one', 'achievements.two', 'achievements.three'], $this->keys($this->forAuthors([$owner->id], null)[$owner->id]));
        $this->assertWarned($log, 'too_many');
    }

    public function test_a_badge_that_cannot_be_drawn_is_left_out_and_warned_about_once_a_day(): void
    {
        $log = Log::spy();
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [$ids[0] => [
            FakeProfileBadgeSource::badge('tournaments.podium'),
            FakeProfileBadgeSource::badge('Not A Key'),
            FakeProfileBadgeSource::badge('achievements.good'),
        ]]));
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [$ids[0] => [
            FakeProfileBadgeSource::badge('achievements.nameless', label: "  \u{202E}\t "),
            FakeProfileBadgeSource::badge('achievements.icon', icon: 'not-an-icon'),
            FakeProfileBadgeSource::badge('achievements.image', imageUrl: 'http://res.cloudinary.com/demo/badge.png'),
        ]]));
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [$ids[0] => [
            FakeProfileBadgeSource::badge('achievements.link', url: 'javascript:alert(1)'),
            FakeProfileBadgeSource::badge('achievements.good'),
            FakeProfileBadgeSource::badge('achievements.other-good'),
        ]]));

        $this->assertSame(['achievements.good', 'achievements.other-good'], $this->keys($this->forAuthors([$owner->id], null)[$owner->id]));
        foreach (['owner', 'key', 'label', 'icon', 'image', 'url', 'duplicate'] as $reason) {
            $this->assertWarned($log, $reason);
        }

        // Once a day: the next request warns about nothing again
        $this->forAuthors([$owner->id], null);
        $log->shouldHaveReceived('warning')->times(7);
    }

    public function test_only_a_cloudinary_image_is_kept(): void
    {
        $log = Log::spy();
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [$ids[0] => [
            FakeProfileBadgeSource::badge('achievements.cloudinary', imageUrl: 'https://res.cloudinary.com/demo/image/upload/w_48,h_48,c_fill/badge.png'),
            FakeProfileBadgeSource::badge('achievements.elsewhere', imageUrl: 'https://images.example.com/badge.png'),
            FakeProfileBadgeSource::badge('achievements.lookalike', imageUrl: 'https://res.cloudinary.com.example.com/badge.png'),
        ]]));
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [$ids[0] => [
            FakeProfileBadgeSource::badge('achievements.port', imageUrl: 'https://res.cloudinary.com:8443/demo/badge.png'),
            FakeProfileBadgeSource::badge('achievements.path', imageUrl: '/images/badge.png'),
        ]]));

        $this->assertSame(['achievements.cloudinary'], $this->keys($this->forAuthors([$owner->id], null)[$owner->id]));
        $this->assertWarned($log, 'image');
    }

    public function test_a_label_made_only_of_invisible_characters_is_left_out(): void
    {
        $log = Log::spy();
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [$ids[0] => [
            FakeProfileBadgeSource::badge('achievements.invisible', label: "\u{200B}\u{2060}\u{FEFF}\u{200D}"),
            FakeProfileBadgeSource::badge('achievements.visible', label: "Ve\u{200B}terana"),
        ]]));

        $badges = $this->forAuthors([$owner->id], null)[$owner->id];

        $this->assertSame(['achievements.visible'], $this->keys($badges));
        $this->assertSame('Veterana', $badges[0]->label);
        $this->assertWarned($log, 'label');
    }

    public function test_a_source_may_answer_ids_in_capitals(): void
    {
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [
            strtoupper($ids[0]) => [FakeProfileBadgeSource::badge('achievements.first')],
        ]));

        $this->assertSame(['achievements.first'], $this->keys($this->forAuthors([$owner->id], null)[$owner->id] ?? []));
    }

    public function test_texts_are_cut_and_lose_their_control_characters(): void
    {
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [$ids[0] => [
            FakeProfileBadgeSource::badge(
                'achievements.long',
                label: "  Gran\u{202E} narración\n".str_repeat('a', 100),
                description: "Línea\u{0007} uno\n".str_repeat('b', 400),
            ),
        ]]));

        $badge = $this->forAuthors([$owner->id], null)[$owner->id][0];

        $this->assertSame(ProfileBadgeDTO::LABEL_MAX_LENGTH, mb_strlen($badge->label));
        $this->assertStringStartsWith('Gran narración aaa', $badge->label);
        $this->assertSame(ProfileBadgeDTO::DESCRIPTION_MAX_LENGTH, mb_strlen($badge->description));
        $this->assertStringStartsWith('Línea uno bbb', $badge->description);
    }

    public function test_a_failing_source_is_left_out_and_the_others_still_give_theirs(): void
    {
        $log = Log::spy();
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $this->source(new FakeProfileBadgeSource(answer: static function (): never {
            throw new RuntimeException('ana@example.com is not here');
        }));
        $this->source(new FakeProfileBadgeSource);

        $this->assertSame(['achievements.first'], $this->keys($this->forAuthors([$owner->id], null)[$owner->id]));
        $log->shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => $context['reason'] === 'failed'
                && $context['exception'] === RuntimeException::class
                && ! str_contains((string) json_encode($context), 'ana@example.com'),
        );
    }

    public function test_answers_in_another_shape_or_about_accounts_not_asked_for_are_ignored(): void
    {
        $log = Log::spy();
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $hidden = $this->owner('Berta', ProfileVisibility::Hidden);
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [$ids[0] => 'no es una lista']));
        $this->source(new FakeProfileBadgeSource(answer: static fn (array $ids): array => [
            $ids[0] => ['achievements.first' => FakeProfileBadgeSource::badge('achievements.first'), new stdClass],
            $hidden->id => [FakeProfileBadgeSource::badge('achievements.secret')],
        ]));

        $this->assertSame(['achievements.first'], $this->keys($this->forAuthors([$owner->id, $hidden->id], null)[$owner->id]));
        $this->assertWarned($log, 'shape');
        $this->assertWarned($log, 'type');
    }

    public function test_a_registered_class_that_is_not_a_source_or_cannot_be_built_is_left_out(): void
    {
        $log = Log::spy();
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $registry = $this->app->make(ProfileBadgeSourceRegistryInterface::class);
        $registry->register(stdClass::class, 'achievements');
        $registry->register('App\\Not\\A\\Class', 'achievements');
        $this->source(new FakeProfileBadgeSource);

        $this->assertSame(['achievements.first'], $this->keys($this->forAuthors([$owner->id], null)[$owner->id]));
        $this->assertWarned($log, 'build');
    }

    public function test_a_failing_access_check_hides_the_badges(): void
    {
        $owner = $this->owner('Ana', ProfileVisibility::Association);
        $membership = Mockery::mock(AssociationMembershipServiceInterface::class);
        $membership->shouldReceive('isAssociationMember')->andThrow(new RuntimeException('roles unreadable'));
        $this->app->instance(AssociationMembershipServiceInterface::class, $membership);
        $source = new FakeProfileBadgeSource;
        $this->source($source);

        $this->assertSame([], $this->forAuthors([$owner->id], UserModel::factory()->create()));
        $this->assertSame([], $source->calls);
    }

    public function test_it_never_throws(): void
    {
        $log = Log::spy();
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $this->source(new FakeProfileBadgeSource);
        $this->app->bind(StoredProfiles::class, static fn (): never => throw new RuntimeException('database gone'));

        $this->assertSame([], $this->app->make(ProfileBadgesInterface::class)->forAuthors([$owner->id], null));
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_warnings_are_remembered_under_the_profiles_prefix(): void
    {
        $owner = $this->owner('Ana', ProfileVisibility::Public);
        $id = $this->source(new FakeProfileBadgeSource(answer: static function (): never {
            throw new RuntimeException('broken');
        }));

        $this->forAuthors([$owner->id], null);

        $this->assertTrue(Cache::has(ProfileContributionRegistry::WARNING_PREFIX.sha1('badges|'.$id.'|failed')));
    }

    private function owner(string $name, ProfileVisibility $visibility): UserModel
    {
        $owner = UserModel::factory()->create(['display_name' => $name]);
        UserProfileModel::query()->whereKey($owner->id)->update(['visibility' => $visibility->value]);

        return $owner;
    }

    /**
     * Registers the source under an id of its own, as a module's class would be.
     */
    private function source(FakeProfileBadgeSource $source, string $module = 'achievements'): string
    {
        $id = 'test-badge-source-'.(++$this->bindings);
        $this->app->instance($id, $source);
        $this->app->make(ProfileBadgeSourceRegistryInterface::class)->register($id, $module);

        return $id;
    }

    /**
     * One request: what the request remembered is forgotten first.
     *
     * @param  list<string>  $ids
     * @return array<string, list<ProfileBadgeDTO>>
     */
    private function forAuthors(array $ids, ?Authenticatable $viewer): array
    {
        $this->app->forgetScopedInstances();

        return $this->app->make(ProfileBadgesInterface::class)->forAuthors($ids, $viewer);
    }

    /**
     * @param  list<string>  $ids
     */
    private function queriesFor(array $ids, ?Authenticatable $viewer): int
    {
        $this->app->forgetScopedInstances();
        // Warm what any request has read already (settings, roles), so only the page's own reads count
        $this->app->make(ProfileBadgesInterface::class)->forAuthors($ids, $viewer);
        $this->app->forgetScopedInstances();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->app->make(ProfileBadgesInterface::class)->forAuthors($ids, $viewer);
        DB::disableQueryLog();

        return count(DB::getQueryLog());
    }

    /**
     * @param  list<ProfileBadgeDTO>  $badges
     * @return list<string>
     */
    private function keys(array $badges): array
    {
        return array_map(static fn (ProfileBadgeDTO $badge): string => $badge->key, $badges);
    }

    private function assertWarned(MockInterface $log, string $reason): void
    {
        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => ($context['reason'] ?? null) === $reason,
        );
    }
}
