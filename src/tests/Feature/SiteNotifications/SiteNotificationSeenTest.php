<?php

declare(strict_types=1);

namespace Tests\Feature\SiteNotifications;

use App\Application\Impersonation\Services\ImpersonationServiceInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Domain\Impersonation\Enums\ImpersonationMode;
use App\Infrastructure\Persistence\Eloquent\Models\SiteNotificationModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use stdClass;
use Tests\Support\SiteNotifications\SiteTestNotification;
use Tests\TestCase;

/**
 * «Seen» apart from «read» (core#800): opening the bell or the «Avisos» page stores when the
 * person last looked, and the bell counts the unread notices that arrived after it. The rows stay
 * unread until opened or marked.
 */
final class SiteNotificationSeenTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(MailCategoryRegistryInterface::class)->register(
            MailCategoryDTO::optional(key: 'example.replies', section: 'Example', label: 'Replies', description: 'D', site: true),
            'example',
        );
    }

    public function test_without_a_mark_every_unread_notice_is_new(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user);
        $this->notify($user);

        $this->actingAs($user)->getJson('/avisos/contador')->assertOk()->assertExactJson(['unread' => 2, 'unseen' => 2]);
        $this->actingAs($user)->get('/')->assertInertia(
            static fn (AssertableInertia $page): AssertableInertia => $page
                ->where('siteNotifications.unread', 2)
                ->where('siteNotifications.unseen', 2),
        );
    }

    public function test_opening_the_bell_sets_the_counter_to_zero_and_keeps_the_notices_unread(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user);
        $this->notify($user);

        $this->actingAs($user)->postJson('/avisos/vistos')->assertOk()->assertExactJson(['unread' => 2, 'unseen' => 0]);

        $this->assertSame(2, SiteNotificationModel::query()->whereNull('read_at')->count());
        $this->actingAs($user)->getJson('/avisos/recientes')
            ->assertJsonPath('unread', 2)
            ->assertJsonPath('unseen', 0)
            ->assertJsonPath('items.0.unread', true)
            ->assertJsonPath('items.1.unread', true);
    }

    public function test_a_notice_arriving_after_the_bell_was_opened_counts(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user);
        $this->actingAs($user)->postJson('/avisos/vistos')->assertOk();

        $this->travel(1)->seconds();
        $this->notify($user);

        $this->actingAs($user)->getJson('/avisos/contador')->assertExactJson(['unread' => 2, 'unseen' => 1]);
    }

    public function test_a_group_that_grows_after_the_bell_was_opened_counts_again(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user, 'forum.topic:1');
        $this->actingAs($user)->postJson('/avisos/vistos')->assertOk();

        $this->travel(1)->seconds();
        $this->notify($user, 'forum.topic:1');

        $this->assertSame(1, SiteNotificationModel::query()->count());
        $this->actingAs($user)->getJson('/avisos/contador')->assertExactJson(['unread' => 1, 'unseen' => 1]);
    }

    public function test_only_unread_visible_notices_count_as_new(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user);
        $this->notify($user);
        $this->notify($user);
        [$read, $hidden] = SiteNotificationModel::query()->orderBy('sort_key')->limit(2)->get()->all();

        $this->actingAs($user)->postJson('/avisos/'.$read->id.'/leido')->assertOk()->assertExactJson(['unread' => 2, 'unseen' => 2]);
        $this->actingAs($user)->postJson('/avisos/'.$hidden->id.'/ocultar')->assertOk()->assertExactJson(['unread' => 1, 'unseen' => 1]);
        $this->actingAs($user)->postJson('/avisos/leidos')->assertOk()->assertExactJson(['unread' => 0, 'unseen' => 0]);
    }

    public function test_marking_a_seen_notice_unread_again_does_not_bring_the_counter_back(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user);
        $notice = SiteNotificationModel::query()->firstOrFail();
        $this->actingAs($user)->get('/avisos/'.$notice->id);
        $this->actingAs($user)->postJson('/avisos/vistos')->assertOk();

        $this->actingAs($user)->postJson('/avisos/'.$notice->id.'/no-leido')->assertOk()->assertExactJson(['unread' => 1, 'unseen' => 0]);
    }

    public function test_the_avisos_page_counts_as_seen(): void
    {
        $user = UserModel::factory()->create();
        $this->notify($user);
        $this->notify($user);

        $this->actingAs($user)->get('/avisos')->assertOk()->assertInertia(
            static fn (AssertableInertia $page): AssertableInertia => $page
                ->where('unread', 2)
                ->where('siteNotifications.unread', 2)
                ->where('siteNotifications.unseen', 0),
        );
        $this->actingAs($user)->getJson('/avisos/contador')->assertExactJson(['unread' => 2, 'unseen' => 0]);
    }

    public function test_each_person_has_their_own_mark(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->notify($user);
        $this->notify($other);

        $this->actingAs($user)->postJson('/avisos/vistos')->assertOk();

        $this->actingAs($other)->getJson('/avisos/contador')->assertExactJson(['unread' => 1, 'unseen' => 1]);
    }

    public function test_the_mark_never_goes_back(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user)->postJson('/avisos/vistos')->assertOk();
        $stored = DB::table('user_site_notification_seen')->where('user_id', $user->id)->value('seen_key');

        $this->travel(-1)->hours();
        $this->actingAs($user)->postJson('/avisos/vistos')->assertOk();

        $this->assertSame((int) $stored, (int) DB::table('user_site_notification_seen')->where('user_id', $user->id)->value('seen_key'));
    }

    public function test_deleting_the_account_takes_its_mark(): void
    {
        $user = UserModel::factory()->create();
        $this->actingAs($user)->postJson('/avisos/vistos')->assertOk();

        $user->forceDelete();

        $this->assertSame(0, DB::table('user_site_notification_seen')->count());
    }

    public function test_anything_that_is_not_an_account_has_nothing_new_and_marks_nothing(): void
    {
        $notices = $this->app->make(SiteNotificationServiceInterface::class);
        $notices->markSeen(new stdClass);

        $this->assertSame(0, $notices->unseenCount(new stdClass));
        $this->assertSame(0, DB::table('user_site_notification_seen')->count());
    }

    public function test_guests_are_refused(): void
    {
        $this->postJson('/avisos/vistos')->assertUnauthorized();
    }

    public function test_looking_while_impersonating_answers_and_stores_nothing(): void
    {
        $admin = UserModel::factory()->admin()->create();
        $member = UserModel::factory()->create();
        $this->notify($member);

        foreach ([ImpersonationMode::ReadOnly, ImpersonationMode::Simulate] as $mode) {
            $this->actingAs($admin);
            app(ImpersonationServiceInterface::class)->start($admin, $member, $mode);

            $this->postJson('/avisos/vistos')->assertOk()->assertJsonPath('unseen', 0);
            $this->get('/avisos')->assertOk();

            $this->assertSame(0, DB::table('user_site_notification_seen')->count(), $mode->value);
            $this->post('/impersonar/salir');
        }
    }

    private function notify(UserModel $user, ?string $groupKey = null): void
    {
        $user->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(title: 'Aviso', url: '/foro/1', groupKey: $groupKey)));
    }
}
