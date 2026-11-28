<?php

declare(strict_types=1);

namespace Tests\Feature\Bots;

use App\Application\Activity\DTOs\ActivityFactDTO;
use App\Application\Activity\DTOs\ActivityHistoryPageDTO;
use App\Application\Activity\DTOs\ActivitySubjectFactsDTO;
use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Application\Activity\Services\ActivityReaderInterface;
use App\Application\Activity\Services\ActivitySourceRegistryInterface;
use App\Application\Authorization\Services\MemberRoleServiceInterface;
use App\Application\Bots\Services\BotRegistryInterface;
use App\Application\Bots\Services\BotsInterface;
use App\Application\DTOs\LoginAttemptOutcome;
use App\Application\Impersonation\Services\ImpersonationAccountsInterface;
use App\Application\Impersonation\Services\ImpersonationEligibilityRule;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Services\AuthServiceInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Domain\Activity\ActivityRole;
use App\Domain\Impersonation\Enums\ImpersonationRefusal;
use App\Domain\Profiles\Events\ProfileMembershipDisplayChanged;
use App\Infrastructure\Bots\BotRegistry;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Filament\Panel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\Support\Activity\FakeActivitySource;
use Tests\Support\Bots\FakeBot;
use Tests\Support\Mail\CategorizedTestNotification;
use Tests\Support\SiteNotifications\SiteTestNotification;
use Tests\TestCase;

/**
 * A bot is not a person (core#863): no profile, no activity, no sign-in, no panel, no impersonation.
 */
final class BotExclusionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string KEY = 'forum.publisher';

    /** @var list<array{string, string}> */
    private array $recorded = [];

    protected function setUp(): void
    {
        parent::setUp();

        $branding = Mockery::mock(SiteBrandingServiceInterface::class);
        $branding->shouldReceive('siteName')->andReturn('Asociación de ejemplo');
        $this->app->instance(SiteBrandingServiceInterface::class, $branding);

        $registry = new BotRegistry($this->app);
        $registry->register(FakeBot::class, 'forum');
        $this->app->instance(BotRegistryInterface::class, $registry);
    }

    private function botId(): string
    {
        return $this->app->make(BotsInterface::class)->id(self::KEY);
    }

    private function bot(string $id): UserModel
    {
        return UserModel::query()->withBots()->findOrFail($id);
    }

    private function recordPublications(): void
    {
        $this->recorded = [];
        $recorded = &$this->recorded;
        $this->app->instance(ActivityPublisherInterface::class, new class($recorded) implements ActivityPublisherInterface
        {
            /** @param list<array{string, string}> $calls */
            public function __construct(private array &$calls) {}

            public function recorded(string $sourceType, string $subjectId): void
            {
                $this->calls[] = [$sourceType, $subjectId];
            }
        });
    }

    public function test_creating_a_bot_creates_no_profile_and_publishes_nothing(): void
    {
        $this->recordPublications();
        Event::fake([ProfileMembershipDisplayChanged::class]);

        $id = $this->botId();

        $this->assertSame(0, DB::table('user_profiles')->where('user_id', $id)->count());
        $this->assertSame(0, DB::table('user_profile_handles')->count());
        $this->assertSame([], $this->recorded);
        Event::assertNotDispatched(ProfileMembershipDisplayChanged::class);
    }

    public function test_changing_deactivating_and_restoring_a_bot_announces_nothing(): void
    {
        $id = $this->botId();
        $this->recordPublications();
        Event::fake([ProfileMembershipDisplayChanged::class]);

        $bot = $this->bot($id);
        $bot->update(['display_name' => 'Heraldo']);
        $bot->update(['avatar_public_id' => 'avatars/x']);
        $bot->delete();
        $bot->restore();

        $this->assertSame(0, DB::table('user_profiles')->where('user_id', $id)->count());
        $this->assertSame([], $this->recorded);
        Event::assertNotDispatched(ProfileMembershipDisplayChanged::class);
    }

    public function test_a_person_still_gets_a_profile_and_announcements(): void
    {
        $this->recordPublications();
        Event::fake([ProfileMembershipDisplayChanged::class]);

        $person = UserModel::factory()->create();
        $person->update(['display_name' => 'Persona nueva']);

        $this->assertSame(1, DB::table('user_profiles')->where('user_id', $person->id)->count());
        $this->assertContains(['core.account', strtolower((string) $person->id)], $this->recorded);
        Event::assertDispatched(ProfileMembershipDisplayChanged::class);
    }

    public function test_the_activity_reader_drops_the_facts_of_bots_in_facts_and_history(): void
    {
        $botId = $this->botId();
        $person = UserModel::factory()->create();
        $personId = strtolower((string) $person->id);
        $at = new DateTimeImmutable('2026-11-20 18:00:00');
        $facts = [
            new ActivityFactDTO($botId, ActivityRole::Player, $at),
            new ActivityFactDTO($personId, ActivityRole::Player, $at),
        ];
        $this->app->bind('bot-exclusion-source', static fn (): FakeActivitySource => new class($facts) extends FakeActivitySource
        {
            /** @param list<ActivityFactDTO> $facts */
            public function __construct(private readonly array $facts) {}

            public function facts(string $subjectId): array
            {
                return $this->facts;
            }

            public function history(?string $cursor, int $limit): ActivityHistoryPageDTO
            {
                return new ActivityHistoryPageDTO([new ActivitySubjectFactsDTO('1', $this->facts)], null);
            }
        });
        $registry = $this->app->make(ActivitySourceRegistryInterface::class);
        $registry->register('bot-exclusion-source', 'example-module');

        $reader = $this->app->make(ActivityReaderInterface::class);

        $found = $reader->facts('example-module.thing', '1');
        $this->assertTrue($found->isRead());
        $this->assertSame([$personId], array_map(static fn (ActivityFactDTO $fact): string => $fact->userId, $found->facts));

        $page = $reader->history('example-module.thing', null, 10)->page;
        $this->assertNotNull($page);
        $this->assertSame([$personId], array_map(static fn (ActivityFactDTO $fact): string => $fact->userId, $page->subjects[0]->facts));
    }

    public function test_a_bot_never_opens_the_panel_even_with_admin_access_by_hand(): void
    {
        $bot = $this->bot($this->botId());
        $role = RoleModel::query()->firstOrCreate(['name' => 'admin'], ['display_name' => 'Administrator', 'is_protected' => true]);
        $bot->roles()->attach($role->id);

        $this->assertFalse($bot->canAccessPanel(Panel::make()->id('admin')));
    }

    public function test_a_bot_cannot_sign_in_even_with_a_known_password(): void
    {
        $bot = $this->bot($this->botId());
        $bot->forceFill(['password' => Hash::make('known-password-123')])->save();

        $attempt = $this->app->make(AuthServiceInterface::class)->attemptLogin($bot->email, 'known-password-123');

        $this->assertSame(LoginAttemptOutcome::Failed, $attempt->outcome);
        $this->assertNull($attempt->userId);
        $this->assertGuest();
    }

    public function test_a_bot_is_never_a_valid_impersonation_target(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $bot = $this->bot($this->botId());
        $rule = $this->app->make(ImpersonationEligibilityRule::class);

        $this->assertSame(ImpersonationRefusal::TargetIsBot, $rule->startRefusal($admin, $bot));
        $this->assertSame(ImpersonationRefusal::TargetIsBot, $rule->continuationRefusal($admin, $bot));
        $this->assertFalse($rule->mayStart($admin, $bot));
    }

    public function test_the_impersonation_lookup_finds_a_bot_so_the_rule_can_name_the_reason(): void
    {
        $id = $this->botId();

        $found = $this->app->make(ImpersonationAccountsInterface::class)->find($id);

        $this->assertInstanceOf(UserModel::class, $found);
        $this->assertTrue($found->isBot());
    }

    public function test_the_member_role_command_gives_a_bot_no_role(): void
    {
        RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        $id = $this->botId();
        UserModel::factory()->create();

        $this->artisanCommand('users:assign-member-role')->assertSuccessful();

        $this->assertSame(0, DB::table('user_role')->where('user_id', $id)->count());
    }

    public function test_a_bot_gets_no_site_notice(): void
    {
        $this->registerCategory(site: true);
        $bot = $this->bot($this->botId());

        $bot->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(
            title: 'Aviso',
            url: '/foro',
            body: 'Texto',
            icon: 'bell',
        )));

        $this->assertSame(0, DB::table('site_notifications')->where('user_id', $bot->getKey())->count());
    }

    public function test_a_bot_gets_no_mail(): void
    {
        $this->registerCategory(site: false);
        $sent = [];
        Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$sent): void {
            $sent[] = $event->notification::class;
        });

        $this->bot($this->botId())->notify(new CategorizedTestNotification('example.replies'));

        $this->assertSame([], $sent);
    }

    public function test_the_base_role_is_never_given_to_a_bot(): void
    {
        RoleModel::query()->firstOrCreate(['name' => 'member'], ['display_name' => 'Member']);
        $bot = $this->bot($this->botId());

        $this->app->make(MemberRoleServiceInterface::class)->assignTo($bot);

        $this->assertSame(0, DB::table('user_role')->where('user_id', $bot->getKey())->count());
        $this->assertSame(0, DB::table('role_audit_log')->count());
    }

    private function registerCategory(bool $site): void
    {
        $this->app->make(MailCategoryRegistryInterface::class)->registerMany([
            MailCategoryDTO::optional(
                key: 'example.replies',
                section: 'Example section',
                label: 'Replies',
                description: 'Replies',
                site: $site,
            ),
        ], 'example');
    }
}
