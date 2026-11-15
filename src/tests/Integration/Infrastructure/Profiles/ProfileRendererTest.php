<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Profiles;

use App\Application\Authorization\Services\AssociationMembershipServiceInterface;
use App\Application\Exceptions\NothingToDrawException;
use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ProfileStatDTO;
use App\Application\Profiles\DTOs\ResolvedProfileDTO;
use App\Application\Profiles\DTOs\ResolvedProfileSectionDTO;
use App\Application\Profiles\DTOs\ResolvedProfileStatDTO;
use App\Application\Profiles\Services\ProfileRendererInterface;
use App\Application\Profiles\Services\ProfileSectionRegistryInterface;
use App\Application\Profiles\Services\ProfileStatRegistryInterface;
use App\Application\Services\TimezoneServiceInterface;
use App\Application\SiteBlocks\Exceptions\NothingToDrawException as SiteBlockNothingToDrawException;
use App\Domain\Profiles\ProfileRegion;
use App\Domain\Profiles\ValueObjects\ProfileLayout;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use App\Infrastructure\Profiles\ProfileContributionRegistry;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Mockery;
use RuntimeException;
use Tests\Support\Profiles\FakeProfileSection;
use Tests\Support\Profiles\FakeProfileStat;
use Tests\TestCase;

/**
 * A public profile drawn for one person: the owner's layout decides order and on/off, and a section or
 * stat that cannot be drawn is left out, never breaking the page.
 */
final class ProfileRendererTest extends TestCase
{
    use LazilyRefreshDatabase;

    private int $bindings = 0;

    protected function setUp(): void
    {
        parent::setUp();

        // Each test warns afresh: the marks live in the default cache for a day
        Cache::flush();
    }

    public function test_the_renderer_is_scoped_to_the_request(): void
    {
        $first = $this->app->make(ProfileRendererInterface::class);
        $this->assertSame($first, $this->app->make(ProfileRendererInterface::class));

        $this->app->forgetScopedInstances();

        $this->assertNotSame($first, $this->app->make(ProfileRendererInterface::class));
    }

    public function test_the_header_says_who_it_is_and_since_when_never_the_account_name(): void
    {
        $this->travelTo(Carbon::parse('2024-06-03 10:00:00', 'UTC'));
        $user = UserModel::factory()->create([
            'name' => 'Ana García López',
            'display_name' => 'Ana García',
            'email' => 'ana@example.com',
            'avatar_public_id' => 'avatars/ana.jpg',
        ]);
        $this->travelBack();
        $this->profileOf($user)->update(['banner_public_id' => 'profiles/banners/2026/11/ana.jpg']);

        $profile = $this->render($user, null);

        $this->assertSame([
            'handle' => 'ana-garcia',
            'publicName' => 'Ana García',
            'avatarPublicId' => 'avatars/ana.jpg',
            'bannerPublicId' => 'profiles/banners/2026/11/ana.jpg',
            'memberSince' => $this->app->make(TimezoneServiceInterface::class)->iso(Carbon::parse('2024-06-03 10:00:00', 'UTC')),
            'showsMembership' => false,
            'isOwner' => false,
        ], $profile->header->toArray());

        $encoded = (string) json_encode($profile->toArray());
        $this->assertStringNotContainsString('López', $encoded);
        $this->assertStringNotContainsString('ana@example.com', $encoded);
    }

    public function test_the_owner_is_told_apart_from_other_viewers(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $other = UserModel::factory()->create(['display_name' => 'Luis']);

        $this->assertTrue($this->render($user, $user)->header->isOwner);
        $this->assertFalse($this->render($user, $other)->header->isOwner);
    }

    public function test_the_membership_badge_shows_only_when_chosen_and_the_owner_is_a_socio(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->membership(true);

        $this->assertFalse($this->render($user, null)->header->showsMembership);

        $this->profileOf($user)->update(['show_membership' => true]);
        $this->app->forgetScopedInstances();
        $this->assertTrue($this->render($user, null)->header->showsMembership);

        $this->membership(false);
        $this->app->forgetScopedInstances();
        $this->assertFalse($this->render($user, null)->header->showsMembership);
    }

    public function test_a_failing_membership_check_hides_the_badge(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->profileOf($user)->update(['show_membership' => true]);
        $membership = Mockery::mock(AssociationMembershipServiceInterface::class);
        $membership->shouldReceive('isAssociationMember')->andThrow(new RuntimeException('Down'));
        $this->app->instance(AssociationMembershipServiceInterface::class, $membership);
        Log::spy();

        $this->assertFalse($this->render($user, null)->header->showsMembership);
    }

    public function test_about_me_is_drawn_in_the_aside_as_lines_and_links(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->profileOf($user)->update(['bio' => "Dirijo los martes.\nMi blog: https://example.com"]);

        $aside = $this->render($user, null)->aside;

        $this->assertCount(1, $aside);
        $this->assertSame([
            'key' => 'core.about',
            'module' => null,
            'component' => 'components/profileSections/AboutSection.vue',
            'region' => 'aside',
            'label' => 'Sobre mí',
            'props' => ['lines' => [
                [['text' => 'Dirijo los martes.', 'href' => null]],
                [['text' => 'Mi blog: ', 'href' => null], ['text' => 'https://example.com', 'href' => 'https://example.com']],
            ]],
        ], $aside[0]->toArray());
    }

    public function test_about_me_never_carries_html_the_owner_wrote(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->profileOf($user)->update(['bio' => '<img src=x onerror=alert(1)> javascript:alert(1)']);

        $props = $this->render($user, null)->aside[0]->props;

        $this->assertSame([[['text' => '<img src=x onerror=alert(1)> javascript:alert(1)', 'href' => null]]], $props['lines']);
    }

    public function test_a_later_request_or_job_reads_the_bio_again(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->profileOf($user)->update(['bio' => 'Antes']);
        $this->assertSame('Antes', $this->render($user, null)->aside[0]->props['lines'][0][0]['text']);

        $this->profileOf($user)->update(['bio' => 'Después']);
        $this->app->forgetScopedInstances();

        $this->assertSame('Después', $this->render($user, null)->aside[0]->props['lines'][0][0]['text']);
    }

    public function test_each_request_or_job_builds_its_own_sections_and_stats_once(): void
    {
        $built = new class
        {
            public int $section = 0;

            public int $stat = 0;
        };
        $this->app->bind('counted-section', static function () use ($built): ProfileSectionInterface {
            $built->section++;

            return new class extends FakeProfileSection
            {
                public function key(): string
                {
                    return 'forum.counted';
                }
            };
        });
        $this->app->bind('counted-stat', static function () use ($built): ProfileStatInterface {
            $built->stat++;

            return new class extends FakeProfileStat
            {
                public function key(): string
                {
                    return 'forum.counted';
                }
            };
        });
        $this->sections(['counted-section']);
        $this->stats(['counted-stat']);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $other = UserModel::factory()->create(['display_name' => 'Luis']);

        // Two profiles in one request: built once, after the registry's own check
        $this->render($user, null);
        $this->render($other, null);
        $this->assertSame([2, 2], [$built->section, $built->stat]);

        // A later request or job builds its own
        $this->app->forgetScopedInstances();
        $this->render($user, null);
        $this->assertSame([3, 3], [$built->section, $built->stat]);
    }

    public function test_what_is_off_is_never_built_to_draw_it(): void
    {
        $built = 0;
        $this->app->bind('quiet-section', static function () use (&$built): ProfileSectionInterface {
            if (++$built > 1) {
                throw new RuntimeException('Built to draw something that is off');
            }

            return new class extends FakeProfileSection
            {
                public function key(): string
                {
                    return 'forum.quiet';
                }

                public function enabledByDefault(): bool
                {
                    return false;
                }
            };
        });
        $this->sections(['quiet-section', $this->section('forum.turned_off')]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->profileOf($user)->update(['layout' => ProfileLayout::fromArray(['sections' => [['key' => 'forum.turned_off', 'enabled' => false]]])]);
        $log = Log::spy();

        $this->assertSame([], $this->render($user, null)->main);
        // Only the registry's check built it
        $this->assertSame(1, $built);
        $log->shouldNotHaveReceived('warning');
    }

    public function test_a_section_built_with_another_key_than_it_registered_with_is_left_out(): void
    {
        $calls = 0;
        $this->app->bind('shifting-section', static function () use (&$calls): ProfileSectionInterface {
            $key = ++$calls === 1 ? 'forum.first' : 'forum.other';

            return new class($key) extends FakeProfileSection
            {
                public function __construct(private readonly string $sectionKey = '') {}

                public function key(): string
                {
                    return $this->sectionKey;
                }
            };
        });
        $this->sections(['shifting-section']);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $log = Log::spy();

        $this->assertSame([], $this->render($user, null)->main);
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message, array $context): bool => ($context['reason'] ?? null) === 'build')->once();
    }

    public function test_the_public_name_comes_from_the_account_never_from_the_caller(): void
    {
        $user = UserModel::factory()->create(['name' => 'Ana García López', 'display_name' => 'Ana']);

        $profile = $this->app->make(ProfileRendererInterface::class)->render(
            new ProfileOwnerDTO($user->id, 'ana', 'Ana García López'),
            null,
        );

        $this->assertSame('Ana', $profile->header->publicName);
    }

    public function test_an_empty_about_me_is_left_out_quietly(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->profileOf($user)->update(['bio' => "  \n "]);
        $log = Log::spy();

        $profile = $this->render($user, null);

        $this->assertSame([], $profile->aside);
        $this->assertTrue($profile->isEmpty());
        $log->shouldNotHaveReceived('warning');
    }

    public function test_module_sections_are_drawn_in_their_region_by_default_order(): void
    {
        $this->sections([
            $this->section('forum.second', order: 20),
            $this->section('forum.first', order: 10),
            $this->section('forum.side', region: ProfileRegion::Aside),
        ]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $viewer = UserModel::factory()->create();

        $profile = $this->render($user, $viewer);

        $this->assertSame(['forum.first', 'forum.second'], $this->keys($profile->main));
        $this->assertSame(['forum.side'], $this->keys($profile->aside));
        $this->assertSame('forum', $profile->main[0]->module);
        $this->assertSame(['owner' => 'ana', 'signedIn' => true], $profile->main[0]->props);
    }

    public function test_the_owner_s_layout_decides_order_and_on_off_and_missing_keys_take_their_defaults(): void
    {
        $this->sections([
            $this->section('forum.a', order: 10),
            $this->section('forum.b', order: 20),
            $this->section('forum.c', order: 30),
            $this->section('forum.off_by_default', order: 5, enabledByDefault: false),
            $this->section('forum.new', order: 1),
        ]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->profileOf($user)->update(['layout' => ProfileLayout::fromArray(['sections' => [
            ['key' => 'forum.c', 'enabled' => true],
            ['key' => 'forum.off_by_default', 'enabled' => true],
            ['key' => 'forum.a', 'enabled' => false],
            ['key' => 'forum.b', 'enabled' => true],
        ]])]);

        // The ones the layout orders first, in its order; then the rest by their default order
        $this->assertSame(['forum.c', 'forum.off_by_default', 'forum.b', 'forum.new'], $this->keys($this->render($user, null)->main));
    }

    public function test_a_section_off_by_default_is_not_drawn_until_the_owner_turns_it_on(): void
    {
        $this->sections([$this->section('forum.quiet', enabledByDefault: false)]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $this->assertSame([], $this->render($user, null)->main);
    }

    public function test_a_layout_key_nobody_registers_is_ignored_and_warned_about_once(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->profileOf($user)->update(['layout' => ProfileLayout::fromArray(['sections' => [
            ['key' => 'game-tables.tables', 'enabled' => true],
            ['key' => 'game-tables.campaigns', 'enabled' => false],
            // Not a key at all (a forged layout): never written to the log
            ['key' => 'Hola <script> ¿qué tal?', 'enabled' => true],
        ], 'stats' => [['key' => 'forum.messages', 'enabled' => true]]])]);
        $log = Log::spy();

        $this->render($user, null);
        $this->app->forgetScopedInstances();
        $this->render($user, null);

        // Only the ones turned on: a key the owner turned off asks for nothing
        $log->shouldHaveReceived('warning')->twice();
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message, array $context): bool => $context === ['key' => 'game-tables.tables', 'reason' => 'unknown', 'kind' => 'section'])->once();
    }

    public function test_an_unavailable_section_is_left_out_without_a_warning(): void
    {
        $this->sections([$this->section('forum.down', available: false)]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $log = Log::spy();

        $this->assertSame([], $this->render($user, null)->main);
        $log->shouldNotHaveReceived('warning');
    }

    public function test_a_section_with_nothing_to_draw_is_left_out_quietly(): void
    {
        $this->sections([$this->section('forum.empty', props: static fn (): never => throw NothingToDrawException::make())]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $log = Log::spy();

        $this->assertSame([], $this->render($user, null)->main);
        $log->shouldNotHaveReceived('warning');
    }

    public function test_the_site_blocks_name_of_nothing_to_draw_is_quiet_too(): void
    {
        $this->sections([$this->section('forum.empty', props: static fn (): never => throw SiteBlockNothingToDrawException::make())]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $log = Log::spy();

        $this->assertSame([], $this->render($user, null)->main);
        $log->shouldNotHaveReceived('warning');
    }

    public function test_a_failing_section_is_left_out_and_only_its_exception_class_is_logged_once(): void
    {
        $this->sections([
            $this->section('forum.broken', props: static fn (): never => throw new RuntimeException('Secret alice@example.com')),
            $this->section('forum.fine'),
        ]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $log = Log::spy();

        $this->assertSame(['forum.fine'], $this->keys($this->render($user, null)->main));
        $this->render($user, null);

        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => $context === ['key' => 'forum.broken', 'reason' => 'failed', 'kind' => 'section', 'exception' => RuntimeException::class],
        )->once();
    }

    public function test_a_section_whose_availability_check_throws_is_left_out_with_a_warning(): void
    {
        $this->sections([$this->section('forum.flaky', available: static fn (): never => throw new RuntimeException('Down'))]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $log = Log::spy();

        $this->assertSame([], $this->render($user, null)->main);
        $log->shouldHaveReceived('warning')->once();
    }

    public function test_props_not_fit_for_the_browser_leave_the_section_out(): void
    {
        $this->sections([
            $this->section('forum.not_json', props: static fn (): array => ['value' => NAN]),
            $this->section('forum.not_keyed', props: static fn (): array => ['a', 'b']),
        ]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $log = Log::spy();

        $this->assertSame([], $this->render($user, null)->main);
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message, array $context): bool => ($context['reason'] ?? null) === 'json')->once();
        $log->shouldHaveReceived('warning')->withArgs(static fn (string $message, array $context): bool => ($context['reason'] ?? null) === 'props')->once();
    }

    public function test_empty_props_go_out_as_an_object(): void
    {
        $this->sections([$this->section('forum.plain', props: static fn (): array => [])]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $this->assertSame('{}', json_encode($this->render($user, null)->main[0]->toArray()['props']));
    }

    public function test_stats_are_counted_for_the_viewer_in_the_owner_s_order(): void
    {
        $this->stats([
            $this->stat('forum.messages', order: 10),
            $this->stat('forum.topics', order: 20),
            $this->stat('forum.likes', order: 30, enabledByDefault: false),
        ]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $this->profileOf($user)->update(['layout' => ProfileLayout::fromArray(['stats' => [['key' => 'forum.topics', 'enabled' => true]]])]);

        $guest = $this->render($user, null)->stats;
        $this->assertSame(['forum.topics', 'forum.messages'], array_map(static fn (ResolvedProfileStatDTO $stat): string => $stat->key, $guest));
        $this->assertSame(['key' => 'forum.topics', 'label' => 'Contador de ejemplo', 'value' => 3, 'url' => '/miembros/ana'], $guest[0]->toArray());

        $this->app->forgetScopedInstances();
        $this->assertSame(7, $this->render($user, UserModel::factory()->create())->stats[0]->value);
    }

    public function test_a_stat_with_nothing_to_count_is_left_out_quietly(): void
    {
        $this->stats([$this->stat('forum.messages', stat: static fn (): ?ProfileStatDTO => null)]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $log = Log::spy();

        $this->assertSame([], $this->render($user, null)->stats);
        $log->shouldNotHaveReceived('warning');
    }

    public function test_a_stat_that_fails_counts_less_than_zero_or_links_where_it_may_not_is_left_out(): void
    {
        $this->stats([
            $this->stat('forum.failing', stat: static fn (): never => throw new RuntimeException('Secret')),
            $this->stat('forum.negative', stat: static fn (): ProfileStatDTO => new ProfileStatDTO(-1)),
            $this->stat('forum.script', stat: static fn (): ProfileStatDTO => new ProfileStatDTO(4, 'javascript:alert(1)')),
            $this->stat('forum.elsewhere', stat: static fn (): ProfileStatDTO => new ProfileStatDTO(4, '//evil.example')),
            $this->stat('forum.unlinked', stat: static fn (): ProfileStatDTO => new ProfileStatDTO(0)),
        ]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        $log = Log::spy();

        $stats = $this->render($user, null)->stats;

        $this->assertSame([['key' => 'forum.unlinked', 'label' => 'Contador de ejemplo', 'value' => 0, 'url' => null]], array_map(static fn (ResolvedProfileStatDTO $stat): array => $stat->toArray(), $stats));
        $log->shouldHaveReceived('warning')->times(4);
    }

    public function test_an_owner_without_a_profile_row_gets_the_defaults(): void
    {
        $this->sections([$this->section('forum.posts')]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        UserProfileModel::query()->whereKey($user->id)->delete();

        $profile = $this->render($user, null);

        $this->assertNull($profile->header->bannerPublicId);
        $this->assertSame(['forum.posts'], $this->keys($profile->main));
    }

    public function test_the_whole_profile_goes_out_in_the_shape_the_page_reads(): void
    {
        $user = UserModel::factory()->create(['display_name' => 'Ana']);

        $this->assertSame('{"header":{"handle":"ana","publicName":"Ana","avatarPublicId":null,"bannerPublicId":null,"memberSince":'.json_encode($this->app->make(TimezoneServiceInterface::class)->iso($user->created_at)).',"showsMembership":false,"isOwner":false},"stats":[],"sections":{"main":[],"aside":[]}}', json_encode($this->render($user, null)->toArray()));
    }

    public function test_warnings_are_remembered_under_the_profiles_prefix(): void
    {
        $this->sections([$this->section('forum.broken', props: static fn (): never => throw new RuntimeException('Down'))]);
        $user = UserModel::factory()->create(['display_name' => 'Ana']);
        Log::spy();

        $this->render($user, null);

        $this->assertTrue(Cache::has(ProfileContributionRegistry::WARNING_PREFIX.sha1('renderer|section|forum.broken|failed')));
    }

    private function render(UserModel $user, ?Authenticatable $viewer): ResolvedProfileDTO
    {
        $handle = UserProfileModel::query()->find($user->id)->handle ?? 'sin-perfil';

        return $this->app->make(ProfileRendererInterface::class)->render(
            new ProfileOwnerDTO($user->id, $handle, $user->display_name),
            $viewer,
        );
    }

    private function profileOf(UserModel $user): UserProfileModel
    {
        return UserProfileModel::query()->findOrFail($user->id);
    }

    private function membership(bool $isMember): void
    {
        $membership = Mockery::mock(AssociationMembershipServiceInterface::class);
        $membership->shouldReceive('isAssociationMember')->andReturn($isMember);
        $this->app->instance(AssociationMembershipServiceInterface::class, $membership);
    }

    /**
     * @param  list<string>  $ids
     */
    private function sections(array $ids): void
    {
        $registry = $this->app->make(ProfileSectionRegistryInterface::class);
        // Only these ones: the core's own section draws nothing without a bio anyway
        $registry->registerMany($ids, 'forum');
    }

    /**
     * @param  list<string>  $ids
     */
    private function stats(array $ids): void
    {
        $this->app->make(ProfileStatRegistryInterface::class)->registerMany($ids, 'forum');
    }

    /**
     * @param  (callable(): array<mixed>)|null  $props
     * @param  bool|(callable(): bool)  $available
     */
    private function section(
        string $key,
        int $order = 100,
        ProfileRegion $region = ProfileRegion::Main,
        bool $enabledByDefault = true,
        bool|callable $available = true,
        ?callable $props = null,
    ): string {
        $section = new class($key, $order, $region, $enabledByDefault, $available, $props) extends FakeProfileSection
        {
            /**
             * @param  bool|(callable(): bool)  $available
             * @param  (callable(): array<mixed>)|null  $propsOf
             */
            public function __construct(
                private readonly string $sectionKey = '',
                private readonly int $position = 100,
                private readonly ProfileRegion $column = ProfileRegion::Main,
                private readonly bool $onByDefault = true,
                private readonly mixed $available = true,
                private readonly mixed $propsOf = null,
            ) {}

            public function key(): string
            {
                return $this->sectionKey;
            }

            public function order(): int
            {
                return $this->position;
            }

            public function region(): ProfileRegion
            {
                return $this->column;
            }

            public function enabledByDefault(): bool
            {
                return $this->onByDefault;
            }

            public function isAvailable(): bool
            {
                return is_callable($this->available) ? ($this->available)() : (bool) $this->available;
            }

            public function props(ProfileOwnerDTO $owner, ?Authenticatable $viewer): array
            {
                return is_callable($this->propsOf) ? ($this->propsOf)() : parent::props($owner, $viewer);
            }
        };

        $id = 'test-section-'.(++$this->bindings);
        $this->app->bind($id, static fn (): ProfileSectionInterface => $section);

        return $id;
    }

    /**
     * @param  (callable(): ?ProfileStatDTO)|null  $stat
     */
    private function stat(string $key, int $order = 100, bool $enabledByDefault = true, ?callable $stat = null): string
    {
        $instance = new class($key, $order, $enabledByDefault, $stat) extends FakeProfileStat
        {
            /**
             * @param  (callable(): ?ProfileStatDTO)|null  $statOf
             */
            public function __construct(
                private readonly string $statKey = '',
                private readonly int $position = 100,
                private readonly bool $onByDefault = true,
                private readonly mixed $statOf = null,
            ) {}

            public function key(): string
            {
                return $this->statKey;
            }

            public function order(): int
            {
                return $this->position;
            }

            public function enabledByDefault(): bool
            {
                return $this->onByDefault;
            }

            public function stat(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ProfileStatDTO
            {
                return is_callable($this->statOf) ? ($this->statOf)() : parent::stat($owner, $viewer);
            }
        };

        $id = 'test-stat-'.(++$this->bindings);
        $this->app->bind($id, static fn (): ProfileStatInterface => $instance);

        return $id;
    }

    /**
     * @param  list<ResolvedProfileSectionDTO>  $sections
     * @return list<string>
     */
    private function keys(array $sections): array
    {
        return array_map(static fn (ResolvedProfileSectionDTO $section): string => $section->key, $sections);
    }
}
