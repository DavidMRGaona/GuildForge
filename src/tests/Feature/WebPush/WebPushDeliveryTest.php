<?php

declare(strict_types=1);

namespace Tests\Feature\WebPush;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\DTOs\MailSubjectDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\Contracts\HasSiteNotification;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\SiteNotifications\Services\SiteNotificationPreferenceServiceInterface;
use App\Application\WebPush\Services\WebPushNotifierInterface;
use App\Application\WebPush\Services\WebPushPreferenceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\WebPush\Jobs\SendWebPushJob;
use ArrayObject;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use RuntimeException;
use Tests\Support\SiteNotifications\SiteTestNotification;
use Tests\Support\WebPush\EnablesWebPush;
use Tests\TestCase;

/**
 * A notice stored in the bell also goes out as a push message, to every browser of the account,
 * when the category is on for push: modules change nothing.
 */
final class WebPushDeliveryTest extends TestCase
{
    use EnablesWebPush;
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
        Queue::fake([SendWebPushJob::class]);
    }

    public function test_each_browser_of_the_account_gets_a_message_with_the_notice_id(): void
    {
        $this->enableWebPush();
        $this->wantsPush(true);
        $user = UserModel::factory()->create();
        $phone = $this->subscribe($user);
        $laptop = $this->subscribe($user, 'https://updates.push.services.mozilla.com/wpush/v2/laptop');
        $this->subscribe(UserModel::factory()->create(), 'https://web.push.apple.com/someone-else');

        $user->notify($this->notification());

        $noticeId = (string) DB::table('site_notifications')->where('user_id', $user->id)->value('id');
        Queue::assertPushed(SendWebPushJob::class, 2);
        foreach ([$phone, $laptop] as $subscription) {
            Queue::assertPushed(SendWebPushJob::class, static fn (SendWebPushJob $job): bool => $job->subscriptionId === $subscription->id
                && $job->noticeId === $noticeId);
        }
    }

    public function test_a_grouped_notice_pushes_the_id_of_its_row(): void
    {
        $this->enableWebPush();
        $this->wantsPush(true);
        $user = UserModel::factory()->create();
        $this->subscribe($user);

        $user->notify($this->notification('topic:1'));
        $user->notify($this->notification('topic:1'));

        $rowId = (string) DB::table('site_notifications')->where('user_id', $user->id)->sole()->id;
        Queue::assertPushed(SendWebPushJob::class, 2);
        Queue::assertPushed(SendWebPushJob::class, static fn (SendWebPushJob $job): bool => $job->noticeId === $rowId);
    }

    public function test_the_preference_is_asked_with_the_category_and_the_subject(): void
    {
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $this->subscribe($user);
        $preference = Mockery::mock(WebPushPreferenceInterface::class);
        $preference->shouldReceive('wantsPush')
            ->once()
            ->withArgs(static fn (string $userId, MailCategoryDTO $category, ?MailSubjectDTO $subject): bool => $userId === $user->id
                && $category->key === 'example.replies'
                && $subject?->type === 'forum.topic')
            ->andReturnFalse();
        $this->app->instance(WebPushPreferenceInterface::class, $preference);

        $user->notify($this->notification(subject: new MailSubjectDTO('forum.topic', 't1', 'La tumba')));

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('site_notifications', 1);
    }

    public function test_until_people_choose_nothing_is_pushed(): void
    {
        // The default preference (NoWebPushPreferences) until core#804 lets people choose
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $this->subscribe($user);

        $user->notify($this->notification());

        Queue::assertNothingPushed();
    }

    public function test_nothing_is_pushed_while_push_is_off(): void
    {
        $this->wantsPush(true);
        $user = UserModel::factory()->create();
        $this->subscribe($user);

        $user->notify($this->notification());

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('site_notifications', 1);
    }

    public function test_nothing_is_pushed_when_the_bell_does_not_get_it(): void
    {
        $this->enableWebPush();
        $this->wantsPush(true);
        $user = UserModel::factory()->create();
        $this->subscribe($user);
        $this->app->make(SiteNotificationPreferenceServiceInterface::class)->update($user, ['example.replies' => false]);

        $user->notify($this->notification());

        Queue::assertNothingPushed();
    }

    public function test_nothing_is_pushed_while_impersonating(): void
    {
        $this->enableWebPush();
        $this->wantsPush(true);
        $user = UserModel::factory()->create();
        $this->subscribe($user);
        $impersonation = Mockery::mock(ImpersonationContextInterface::class);
        $impersonation->shouldReceive('isActive')->andReturnTrue();
        $this->app->instance(ImpersonationContextInterface::class, $impersonation);

        $user->notify($this->notification());

        Queue::assertNothingPushed();
    }

    public function test_a_failing_preference_never_costs_the_bell_or_the_mail(): void
    {
        $sent = $this->sentChannels();
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $this->subscribe($user);
        $preference = Mockery::mock(WebPushPreferenceInterface::class);
        $preference->shouldReceive('wantsPush')->andThrow(new RuntimeException('preferences unreadable'));
        $this->app->instance(WebPushPreferenceInterface::class, $preference);

        $user->notify($this->notification(channels: [HasSiteNotification::CHANNEL, 'mail']));

        Queue::assertNothingPushed();
        $this->assertDatabaseCount('site_notifications', 1);
        $this->assertSame([HasSiteNotification::CHANNEL, 'mail'], $sent->getArrayCopy());
    }

    public function test_a_failing_notifier_never_costs_the_bell_or_the_mail(): void
    {
        $sent = $this->sentChannels();
        $this->enableWebPush();
        $user = UserModel::factory()->create();
        $notifier = Mockery::mock(WebPushNotifierInterface::class);
        $notifier->shouldReceive('notify')->andThrow(new RuntimeException('push is broken'));
        $this->app->instance(WebPushNotifierInterface::class, $notifier);

        $user->notify($this->notification(channels: [HasSiteNotification::CHANNEL, 'mail']));

        $this->assertDatabaseCount('site_notifications', 1);
        $this->assertSame([HasSiteNotification::CHANNEL, 'mail'], $sent->getArrayCopy());
    }

    /**
     * @return ArrayObject<int, string> The channels each notification went out through, in order.
     */
    private function sentChannels(): ArrayObject
    {
        $sent = new ArrayObject;
        Event::listen(NotificationSent::class, static function (NotificationSent $event) use ($sent): void {
            $sent->append($event->channel);
        });

        return $sent;
    }

    private function wantsPush(bool $wants): void
    {
        $preference = Mockery::mock(WebPushPreferenceInterface::class);
        $preference->shouldReceive('wantsPush')->andReturn($wants);
        $this->app->instance(WebPushPreferenceInterface::class, $preference);
    }

    /**
     * @param  list<string>  $channels
     */
    private function notification(?string $groupKey = null, ?MailSubjectDTO $subject = null, array $channels = [HasSiteNotification::CHANNEL]): SiteTestNotification
    {
        return new SiteTestNotification('example.replies', new SiteNotificationDTO(
            title: 'Ana respondió en «La tumba»',
            url: '/foro/temas/la-tumba#m42',
            groupKey: $groupKey,
        ), $subject, channels: $channels);
    }
}
