<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\WebPush;

use App\Application\Mail\DTOs\MailCategoryDTO;
use App\Application\Mail\Services\MailCategoryRegistryInterface;
use App\Application\SiteNotifications\DTOs\SiteNotificationDTO;
use App\Application\WebPush\DTOs\WebPushSubscriptionDTO;
use App\Application\WebPush\Services\WebPushSenderInterface;
use App\Domain\WebPush\WebPushDeliveryOutcome;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\WebPushSubscriptionModel;
use App\Infrastructure\WebPush\Jobs\SendWebPushJob;
use App\Infrastructure\WebPush\WebPushRetryLaterException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\SiteNotifications\SiteTestNotification;
use Tests\Support\WebPush\EnablesWebPush;
use Tests\TestCase;

final class SendWebPushJobTest extends TestCase
{
    use EnablesWebPush;
    use LazilyRefreshDatabase;

    /** @var list<array{endpoint: string, payload: string}> */
    private array $sent = [];

    private WebPushDeliveryOutcome $outcome = WebPushDeliveryOutcome::Delivered;

    protected function setUp(): void
    {
        parent::setUp();

        $this->enableWebPush();
        $this->app->make(MailCategoryRegistryInterface::class)->register(MailCategoryDTO::optional(
            key: 'example.replies',
            section: 'Example section',
            label: 'Replies',
            description: 'Replies in topics you follow',
            site: true,
        ), 'example');
        $test = $this;
        $this->app->instance(WebPushSenderInterface::class, new readonly class($test) implements WebPushSenderInterface
        {
            public function __construct(private SendWebPushJobTest $test) {}

            public function send(WebPushSubscriptionDTO $subscription, string $payload): WebPushDeliveryOutcome
            {
                return $this->test->record($subscription->endpoint, $payload);
            }
        });
    }

    public function record(string $endpoint, string $payload): WebPushDeliveryOutcome
    {
        $this->sent[] = ['endpoint' => $endpoint, 'payload' => $payload];

        return $this->outcome;
    }

    public function test_the_message_carries_the_notice_id_and_nothing_else(): void
    {
        [$subscription, $noticeId] = $this->subscribedWithNotice();

        $this->handle(new SendWebPushJob($subscription->id, $noticeId));

        $this->assertSame([['endpoint' => self::ENDPOINT, 'payload' => json_encode(['id' => $noticeId])]], $this->sent);
        $this->assertNotNull($subscription->fresh()?->last_used_at);
    }

    public function test_a_subscription_the_push_service_no_longer_knows_is_deleted(): void
    {
        [$subscription, $noticeId] = $this->subscribedWithNotice();
        $this->outcome = WebPushDeliveryOutcome::Gone;

        $this->handle(new SendWebPushJob($subscription->id, $noticeId));

        $this->assertNull(WebPushSubscriptionModel::query()->find($subscription->id));
    }

    public function test_a_busy_push_service_is_tried_again_later(): void
    {
        [$subscription, $noticeId] = $this->subscribedWithNotice();
        $this->outcome = WebPushDeliveryOutcome::RetryLater;

        $this->expectException(WebPushRetryLaterException::class);

        $this->handle(new SendWebPushJob($subscription->id, $noticeId));
    }

    public function test_a_refused_message_keeps_the_subscription(): void
    {
        [$subscription, $noticeId] = $this->subscribedWithNotice();
        $this->outcome = WebPushDeliveryOutcome::Failed;

        $this->handle(new SendWebPushJob($subscription->id, $noticeId));

        $this->assertNotNull(WebPushSubscriptionModel::query()->find($subscription->id));
        $this->assertNull($subscription->fresh()?->last_used_at);
    }

    public function test_a_notice_read_or_hidden_meanwhile_is_not_pushed(): void
    {
        [$subscription, $noticeId] = $this->subscribedWithNotice();
        DB::table('site_notifications')->where('id', $noticeId)->update(['read_at' => now()]);

        $this->handle(new SendWebPushJob($subscription->id, $noticeId));

        $this->assertSame([], $this->sent);
    }

    public function test_nothing_is_sent_once_push_is_off_or_the_subscription_is_gone(): void
    {
        [$subscription, $noticeId] = $this->subscribedWithNotice();

        config(['web-push.private_key' => null]);
        $this->handle(new SendWebPushJob($subscription->id, $noticeId));
        $this->enableWebPush();
        $subscription->delete();
        $this->handle(new SendWebPushJob($subscription->id, $noticeId));

        $this->assertSame([], $this->sent);
    }

    public function test_a_notice_of_another_account_is_never_pushed(): void
    {
        [$subscription] = $this->subscribedWithNotice();
        [, $otherNotice] = $this->subscribedWithNotice('https://web.push.apple.com/other');

        $this->handle(new SendWebPushJob($subscription->id, $otherNotice));

        $this->assertSame([], $this->sent);
    }

    public function test_it_runs_after_the_commit_and_is_tried_a_few_times(): void
    {
        $job = new SendWebPushJob('a', 'b');

        $this->assertTrue($job->afterCommit);
        $this->assertSame(3, $job->tries);
        $this->assertSame([60, 300], $job->backoff());
    }

    /**
     * @return array{WebPushSubscriptionModel, string}
     */
    private function subscribedWithNotice(string $endpoint = self::ENDPOINT): array
    {
        $user = UserModel::factory()->create();
        $subscription = $this->subscribe($user, $endpoint);
        $user->notify(new SiteTestNotification('example.replies', new SiteNotificationDTO(title: 'Aviso', url: '/avisos')));

        return [$subscription, (string) DB::table('site_notifications')->where('user_id', $user->id)->value('id')];
    }

    private function handle(SendWebPushJob $job): void
    {
        $this->app->call($job->handle(...));
    }
}
