<?php

declare(strict_types=1);

namespace Tests\Feature\Realtime;

use App\Application\Realtime\Services\RealtimePublisherInterface;
use App\Infrastructure\Realtime\RealtimeBroadcast;
use Illuminate\Broadcasting\BroadcastEvent;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\Support\Realtime\EnablesRealtime;
use Tests\TestCase;

final class RealtimePublisherTest extends TestCase
{
    use EnablesRealtime;
    use LazilyRefreshDatabase;

    private const string DISCUSSION = '0198f1c2-7a3b-7c4d-9e5f-a1b2c3d4e5f6';

    public function test_a_message_is_broadcast_on_its_private_channel(): void
    {
        $this->enableRealtime();
        Event::fake([RealtimeBroadcast::class]);

        $this->publisher()->publish('forum.discussion.'.self::DISCUSSION, 'post.created', ['number' => 42]);

        Event::assertDispatched(RealtimeBroadcast::class, fn (RealtimeBroadcast $message): bool => $message->broadcastOn()->name === 'private-forum.discussion.'.self::DISCUSSION
            && $message->broadcastAs() === 'post.created'
            && $message->broadcastWith() === ['number' => 42]);
    }

    public function test_the_message_goes_through_the_queue_and_is_tried_once(): void
    {
        $this->enableRealtime();
        Queue::fake();

        $this->publisher()->publish('forum.discussion.'.self::DISCUSSION, 'post.created');

        Queue::assertPushed(BroadcastEvent::class, static fn (BroadcastEvent $job): bool => $job->tries === 1
            && $job->event instanceof RealtimeBroadcast);
    }

    public function test_nothing_is_sent_without_realtime(): void
    {
        config(['broadcasting.default' => 'log']);
        Event::fake([RealtimeBroadcast::class]);
        Queue::fake();

        $this->publisher()->publish('forum.discussion.'.self::DISCUSSION, 'post.created', ['number' => 42]);

        Event::assertNotDispatched(RealtimeBroadcast::class);
        Queue::assertNothingPushed();
    }

    public function test_it_waits_for_the_transaction_and_sends_nothing_if_it_rolls_back(): void
    {
        $this->enableRealtime();
        Event::fake([RealtimeBroadcast::class]);

        DB::transaction(function (): void {
            $this->publisher()->publish('forum.discussion.'.self::DISCUSSION, 'post.created', ['number' => 1]);

            Event::assertNotDispatched(RealtimeBroadcast::class);
        });

        Event::assertDispatchedTimes(RealtimeBroadcast::class, 1);

        try {
            DB::transaction(function (): void {
                $this->publisher()->publish('forum.discussion.'.self::DISCUSSION, 'post.created', ['number' => 2]);

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException) {
        }

        Event::assertDispatchedTimes(RealtimeBroadcast::class, 1);
    }

    public function test_content_is_never_broadcast(): void
    {
        $this->enableRealtime();
        Event::fake([RealtimeBroadcast::class]);
        $log = Log::spy();

        $this->publisher()->publish('forum.discussion.'.self::DISCUSSION, 'post.created', ['title' => 'La tumba']);

        Event::assertNotDispatched(RealtimeBroadcast::class);
        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => str_contains($message, 'ids and numbers')
                && ($context['channel'] ?? null) === 'forum.discussion.'.self::DISCUSSION
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'La tumba'),
        )->once();
    }

    public function test_an_invalid_channel_or_event_name_is_not_broadcast(): void
    {
        $this->enableRealtime();
        Event::fake([RealtimeBroadcast::class]);
        $log = Log::spy();

        $this->publisher()->publish('private-forum.discussion.1', 'post.created');
        $this->publisher()->publish('forum.discussion.{discussion}', 'post.created');
        $this->publisher()->publish('forum.discussion.1', 'client-typing');

        Event::assertNotDispatched(RealtimeBroadcast::class);
        $log->shouldHaveReceived('warning')->times(3);
    }

    public function test_a_failure_to_queue_never_breaks_the_caller(): void
    {
        $this->enableRealtime();
        Queue::fake();
        $log = Log::spy();
        Event::listen(RealtimeBroadcast::class, static function (): never {
            throw new RuntimeException('Queue down');
        });

        $this->publisher()->publish('forum.discussion.'.self::DISCUSSION, 'post.created');

        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message): bool => str_contains($message, 'could not be sent'),
        )->once();
    }

    private function publisher(): RealtimePublisherInterface
    {
        return $this->app->make(RealtimePublisherInterface::class);
    }
}
