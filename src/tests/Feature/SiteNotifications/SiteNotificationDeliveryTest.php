<?php

declare(strict_types=1);

namespace Tests\Feature\SiteNotifications;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\Mail\Services\MailPauseServiceInterface;
use App\Application\Mail\Services\MailSubjectMuteServiceInterface;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\DTOs\SiteNotificationGroupDTO;
use App\Application\SiteNotifications\Services\SiteNotificationPreferenceServiceInterface;
use App\Application\SiteNotifications\Services\SiteNotificationServiceInterface;
use App\Domain\Mail\Enums\MailPauseDuration;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Tests\Support\SiteNotifications\SiteTestNotification;
use Tests\TestCase;

/**
 * What reaches the notification centre: only accounts that want it, never about a muted thing
 * or one's own doing, and unread notices of one group as one row.
 */
final class SiteNotificationDeliveryTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(MailCategoryRegistryInterface::class)->registerMany([
            MailCategoryDTO::optional(
                key: 'example.replies',
                section: 'Example section',
                label: 'Replies',
                description: 'Replies in topics you follow',
                site: true,
            ),
            MailCategoryDTO::optional(
                key: 'example.likes',
                section: 'Example section',
                label: 'Likes',
                description: 'Likes on your posts',
                site: true,
                mail: false,
            ),
            MailCategoryDTO::mandatory(
                key: 'example.moderation',
                section: 'Example section',
                label: 'Moderation',
                description: 'What moderators did with your posts',
                reason: 'It changes what you wrote',
                site: true,
            ),
            MailCategoryDTO::optional(
                key: 'example.mail-only',
                section: 'Example section',
                label: 'Mail only',
                description: 'Never shown on the site',
            ),
        ], 'example');
    }

    public function test_a_notice_is_stored_for_the_account(): void
    {
        $user = UserModel::factory()->create();

        $user->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(
            title: 'Ana respondió en «La tumba»',
            url: '/foro/temas/la-tumba#m42',
            body: 'Entro en la cripta.',
            icon: 'message-square',
        )));

        $items = $this->service()->latest($user, 10);

        $this->assertCount(1, $items);
        $this->assertSame('Ana respondió en «La tumba»', $items[0]->title);
        $this->assertSame('/foro/temas/la-tumba#m42', $items[0]->url);
        $this->assertSame('Entro en la cripta.', $items[0]->body);
        $this->assertSame('message-square', $items[0]->icon);
        $this->assertSame(1, $items[0]->count);
        $this->assertTrue($items[0]->unread);
        $this->assertSame(1, $this->service()->unreadCount($user));
        $this->assertDatabaseHas('site_notifications', ['user_id' => $user->id, 'category' => 'example.replies']);
    }

    public function test_the_channel_counts_as_sent(): void
    {
        $sent = [];
        Event::listen(NotificationSent::class, static function (NotificationSent $event) use (&$sent): void {
            $sent[] = $event->channel;
        });
        $user = UserModel::factory()->create();

        $user->notify(new SiteTestNotification('example.replies', $this->notice()));

        $this->assertSame([HasSiteNotification::CHANNEL], $sent);
    }

    public function test_a_category_turned_off_on_the_site_stores_nothing(): void
    {
        $user = UserModel::factory()->create();
        $this->app->make(SiteNotificationPreferenceServiceInterface::class)->update($user, ['example.replies' => false]);

        $user->notify(new SiteTestNotification('example.replies', $this->notice()));

        $this->assertSame(0, $this->service()->unreadCount($user));
    }

    public function test_a_mandatory_category_is_always_shown(): void
    {
        $user = UserModel::factory()->create();

        $user->notify(new SiteTestNotification('example.moderation', $this->notice()));

        $this->assertSame(1, $this->service()->unreadCount($user));
    }

    public function test_a_muted_subject_gets_no_notice_but_others_do(): void
    {
        $user = UserModel::factory()->create();
        $topic = new MailSubjectDTO('example.topic', 'abc', 'La tumba');
        $this->app->make(MailSubjectMuteServiceInterface::class)->mute($user, $topic);

        $user->notify(new SiteTestNotification('example.replies', $this->notice(), $topic));
        $user->notify(new SiteTestNotification('example.replies', $this->notice(), new MailSubjectDTO('example.topic', 'other', 'Otro')));

        $this->assertSame(1, $this->service()->unreadCount($user));
    }

    public function test_a_muted_subject_still_gets_mandatory_notices(): void
    {
        $user = UserModel::factory()->create();
        $topic = new MailSubjectDTO('example.topic', 'abc', 'La tumba');
        $this->app->make(MailSubjectMuteServiceInterface::class)->mute($user, $topic);

        $user->notify(new SiteTestNotification('example.moderation', $this->notice(), $topic));

        $this->assertSame(1, $this->service()->unreadCount($user));
    }

    public function test_a_mail_pause_does_not_pause_the_notices(): void
    {
        $user = UserModel::factory()->create();
        $this->app->make(MailPauseServiceInterface::class)->pause($user, MailPauseDuration::Indefinite);

        $user->notify(new SiteTestNotification('example.replies', $this->notice()));

        $this->assertSame(1, $this->service()->unreadCount($user));
    }

    public function test_a_notice_the_module_withdraws_stores_nothing(): void
    {
        $user = UserModel::factory()->create();

        $user->notify(new SiteTestNotification('example.replies', null));

        $this->assertSame(0, $this->service()->unreadCount($user));
    }

    public function test_a_notice_that_cannot_be_built_stores_nothing_and_warns(): void
    {
        $log = Log::spy();
        $user = UserModel::factory()->create();

        $user->notify(new SiteTestNotification('example.replies', $this->notice(), failing: true));

        $this->assertSame(0, $this->service()->unreadCount($user));
        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message): bool => str_contains($message, 'could not be built'),
        )->once();
    }

    public function test_nobody_is_notified_of_what_they_did_themselves(): void
    {
        $user = UserModel::factory()->create();

        $user->notify(new SiteTestNotification('example.replies', $this->notice(actorId: $user->id)));

        $this->assertSame(0, $this->service()->unreadCount($user));
    }

    public function test_anonymized_and_deactivated_accounts_get_nothing(): void
    {
        $anonymized = UserModel::factory()->create(['anonymized_at' => now()]);
        $deactivated = UserModel::factory()->create();
        $deactivated->delete();

        $anonymized->notify(new SiteTestNotification('example.replies', $this->notice()));
        $deactivated->notify(new SiteTestNotification('example.replies', $this->notice()));

        $this->assertDatabaseCount('site_notifications', 0);
    }

    public function test_a_notification_without_a_site_category_stores_nothing_and_warns_once(): void
    {
        $log = Log::spy();
        $user = UserModel::factory()->create();

        $user->notify(new SiteTestNotification('example.mail-only', $this->notice()));
        $user->notify(new SiteTestNotification('example.mail-only', $this->notice()));
        $user->notify(new SiteTestNotification('example.unknown', $this->notice()));

        $this->assertDatabaseCount('site_notifications', 0);
        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message): bool => str_contains($message, 'without a mail category that offers site notices'),
        )->once();
    }

    public function test_a_site_only_category_is_never_mailed(): void
    {
        $sent = [];
        Event::listen(NotificationSent::class, static function (NotificationSent $event) use (&$sent): void {
            $sent[] = $event->channel;
        });
        $user = UserModel::factory()->create();

        $user->notify(new SiteTestNotification('example.likes', $this->notice(), channels: ['mail', HasSiteNotification::CHANNEL]));

        $this->assertSame([HasSiteNotification::CHANNEL], $sent);
        $this->assertSame(1, $this->service()->unreadCount($user));
    }

    public function test_unread_notices_of_one_group_become_one_row(): void
    {
        $user = UserModel::factory()->create();
        $ana = UserModel::factory()->create();
        $luis = UserModel::factory()->create();

        $user->notify(new SiteTestNotification('example.replies', $this->reply('Ana', $ana->id, '/foro/t/1#m1')));
        $user->notify(new SiteTestNotification('example.replies', $this->reply('Luis', $luis->id, '/foro/t/1#m2')));
        $user->notify(new SiteTestNotification('example.replies', $this->reply('Ana', $ana->id, '/foro/t/1#m3')));

        $items = $this->service()->latest($user, 10);

        $this->assertCount(1, $items);
        $this->assertSame(1, $this->service()->unreadCount($user));
        $this->assertSame(3, $items[0]->count);
        // The newest notice leads: its link and its actor, with the distinct others counted
        $this->assertSame('/foro/t/1#m3', $items[0]->url);
        $this->assertSame('Ana y 1 más respondieron en «Tema»', $items[0]->title);
    }

    public function test_a_read_group_starts_a_new_row(): void
    {
        $user = UserModel::factory()->create();
        $user->notify(new SiteTestNotification('example.replies', $this->reply('Ana', 'actor-1', '/foro/t/1#m1')));
        $first = $this->service()->latest($user, 10)[0];
        $this->service()->open($user, $first->id);

        $user->notify(new SiteTestNotification('example.replies', $this->reply('Luis', 'actor-2', '/foro/t/1#m2')));

        $items = $this->service()->latest($user, 10);
        $this->assertCount(2, $items);
        $this->assertSame('Luis respondió en «Tema»', $items[0]->title);
        $this->assertSame(1, $items[0]->count);
        $this->assertSame(1, $this->service()->unreadCount($user));
    }

    public function test_groups_are_per_account_and_per_key(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();

        $user->notify(new SiteTestNotification('example.replies', $this->reply('Ana', 'actor-1', '/foro/t/1', 'forum.topic:1')));
        $user->notify(new SiteTestNotification('example.replies', $this->reply('Ana', 'actor-1', '/foro/t/2', 'forum.topic:2')));
        $other->notify(new SiteTestNotification('example.replies', $this->reply('Ana', 'actor-1', '/foro/t/1', 'forum.topic:1')));

        $this->assertSame(2, $this->service()->unreadCount($user));
        $this->assertSame(1, $this->service()->unreadCount($other));
    }

    public function test_without_actor_ids_the_others_are_the_other_notices(): void
    {
        $user = UserModel::factory()->create();

        foreach ([1, 2, 3] as $n) {
            $user->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(
                title: 'Nueva respuesta',
                url: '/foro/t/1#m'.$n,
                groupKey: 'forum.topic:1',
                groupedTitle: static fn (SiteNotificationGroupDTO $group): string => sprintf('%d respuestas, %d más', $group->count, $group->otherActors),
            )));
        }

        $this->assertSame('3 respuestas, 2 más', $this->service()->latest($user, 10)[0]->title);
    }

    private function notice(?string $actorId = null): SiteNotificationDTO
    {
        return new SiteNotificationDTO(title: 'Nueva respuesta', url: '/foro/temas/1', actorId: $actorId);
    }

    private function reply(string $actor, string $actorId, string $url, string $group = 'forum.topic:1'): SiteNotificationDTO
    {
        return new SiteNotificationDTO(
            title: $actor.' respondió en «Tema»',
            url: $url,
            groupKey: $group,
            actorId: $actorId,
            groupedTitle: static fn (SiteNotificationGroupDTO $g): string => sprintf('%s y %d más respondieron en «Tema»', $actor, $g->otherActors),
        );
    }

    private function service(): SiteNotificationServiceInterface
    {
        return $this->app->make(SiteNotificationServiceInterface::class);
    }
}
