<?php

declare(strict_types=1);

namespace Tests\Feature\SiteNotifications;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\Services\SiteNotificationPreferenceServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Realtime\RealtimeBroadcast;
use App\Infrastructure\SiteNotifications\SiteNotificationsRealtimeChannel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\Support\Realtime\EnablesRealtime;
use Tests\Support\SiteNotifications\SiteTestNotification;
use Tests\TestCase;

/**
 * The bell hears of a new notice at once when real time is on: a message without content on the
 * account's own channel, after which it asks for the counter as it always did.
 */
final class SiteNotificationRealtimeTest extends TestCase
{
    use EnablesRealtime;
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->make(MailCategoryRegistryInterface::class)->register(MailCategoryDTO::optional(
            key: 'example.replies',
            section: 'Example section',
            label: 'Replies',
            description: 'Replies in topics you follow',
            site: true,
        ), 'example');
    }

    public function test_a_delivered_notice_is_announced_on_the_account_channel(): void
    {
        $this->enableRealtime();
        Event::fake([RealtimeBroadcast::class]);
        $user = UserModel::factory()->create();

        $user->notify($this->notification());

        Event::assertDispatched(RealtimeBroadcast::class, static fn (RealtimeBroadcast $message): bool => $message->broadcastOn()->name === 'private-core.site-notifications.'.$user->id
            && $message->broadcastAs() === SiteNotificationsRealtimeChannel::DELIVERED
            && $message->broadcastWith() === []);
    }

    public function test_a_notice_the_account_does_not_want_is_not_announced(): void
    {
        $this->enableRealtime();
        Event::fake([RealtimeBroadcast::class]);
        $user = UserModel::factory()->create();
        $this->app->make(SiteNotificationPreferenceServiceInterface::class)->update($user, ['example.replies' => false]);

        $user->notify($this->notification());

        Event::assertNotDispatched(RealtimeBroadcast::class);
    }

    public function test_nothing_is_announced_without_realtime_and_the_notice_is_still_stored(): void
    {
        config(['broadcasting.default' => 'log']);
        Event::fake([RealtimeBroadcast::class]);
        $user = UserModel::factory()->create();

        $user->notify($this->notification());

        Event::assertNotDispatched(RealtimeBroadcast::class);
        $this->assertDatabaseCount('site_notifications', 1);
    }

    public function test_the_page_names_the_bell_channel_only_with_realtime(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->get('/')
            ->assertInertia(fn ($page) => $page->where('siteNotifications.channel', null)->where('realtime', null));

        $this->enableRealtime();

        $this->actingAs($user)->get('/')
            ->assertInertia(fn ($page) => $page
                ->where('siteNotifications.channel', 'core.site-notifications.'.$user->id)
                ->where('realtime.key', self::REALTIME_KEY)
                ->where('realtime.host', 'example.test')
                ->where('realtime.port', 443)
                ->where('realtime.tls', true)
                ->where('realtime.authEndpoint', '/broadcasting/auth'));
    }

    public function test_a_guest_gets_no_realtime_settings(): void
    {
        $this->enableRealtime();

        $this->get('/')->assertInertia(fn ($page) => $page->where('realtime', null));
    }

    private function notification(): SiteTestNotification
    {
        return new SiteTestNotification('example.replies', new SiteNotificationDTO(
            title: 'Ana respondió en «La tumba»',
            url: '/foro/temas/la-tumba#m42',
        ));
    }
}
