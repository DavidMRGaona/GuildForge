<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Infrastructure\Impersonation\SafetyNet\ImpersonationSafetyNet;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Mockery;
use ReflectionProperty;
use RuntimeException;
use Tests\TestCase;

final class EventActivityPublisherTest extends TestCase
{
    public function test_it_announces_a_subject_once_the_transaction_commits(): void
    {
        Event::fake([ActivityRecorded::class]);
        $publisher = $this->publisher();

        DB::transaction(static function () use ($publisher): void {
            $publisher->recorded('game-tables.session', '42');

            Event::assertNotDispatched(ActivityRecorded::class);
        });

        Event::assertDispatched(
            ActivityRecorded::class,
            static fn (ActivityRecorded $event): bool => $event->sourceType === 'game-tables.session' && $event->subjectId === '42',
        );
    }

    public function test_a_subject_published_several_times_in_one_transaction_is_announced_once(): void
    {
        Event::fake([ActivityRecorded::class]);
        $publisher = $this->publisher();

        DB::transaction(static function () use ($publisher): void {
            $publisher->recorded('game-tables.session', '0195F3A2-7C1E-7D4B-9A8E-2F6C1B3D4E61');
            $publisher->recorded('game-tables.session', '0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e61');
            $publisher->recorded('game-tables.session', '43');
        });

        Event::assertDispatchedTimes(ActivityRecorded::class, 2);
        Event::assertDispatched(
            ActivityRecorded::class,
            static fn (ActivityRecorded $event): bool => $event->subjectId === '0195f3a2-7c1e-7d4b-9a8e-2f6c1b3d4e61',
        );
    }

    public function test_a_subject_published_in_a_rolled_back_transaction_is_announced_by_the_next_one(): void
    {
        Event::fake([ActivityRecorded::class]);
        $publisher = $this->publisher();

        DB::beginTransaction();
        $publisher->recorded('game-tables.session', '42');
        DB::rollBack();

        DB::transaction(static fn () => $publisher->recorded('game-tables.session', '42'));

        Event::assertDispatchedTimes(ActivityRecorded::class, 1);
    }

    public function test_savepoints_do_not_split_what_one_transaction_publishes(): void
    {
        Event::fake([ActivityRecorded::class]);
        $publisher = $this->publisher();

        DB::transaction(static function () use ($publisher): void {
            $publisher->recorded('core.account', '42');
            DB::transaction(static fn () => $publisher->recorded('core.account', '42'));
            DB::transaction(static function () use ($publisher): void {
                DB::transaction(static fn () => $publisher->recorded('core.account', '42'));
            });
            DB::transaction(static fn () => $publisher->recorded('core.account', '43'));
        });

        Event::assertDispatchedTimes(ActivityRecorded::class, 2);
    }

    public function test_a_subject_published_in_a_savepoint_that_rolls_back_is_still_announced_by_the_next_publication(): void
    {
        Event::fake([ActivityRecorded::class]);
        $publisher = $this->publisher();

        DB::transaction(static function () use ($publisher): void {
            try {
                DB::transaction(static function () use ($publisher): void {
                    $publisher->recorded('core.account', '42');

                    throw new RuntimeException('The savepoint fails.');
                });
            } catch (RuntimeException) {
                // The savepoint is undone; the outer transaction goes on
            }

            DB::transaction(static fn () => $publisher->recorded('core.account', '42'));
        });

        Event::assertDispatchedTimes(ActivityRecorded::class, 1);
    }

    public function test_it_announces_nothing_when_the_transaction_rolls_back(): void
    {
        Event::fake([ActivityRecorded::class]);
        $publisher = $this->publisher();

        DB::beginTransaction();
        $publisher->recorded('game-tables.session', '42');
        DB::rollBack();

        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_outside_a_transaction_it_announces_at_once(): void
    {
        Event::fake([ActivityRecorded::class]);

        $this->publisher()->recorded('forum.post', '7');

        Event::assertDispatchedTimes(ActivityRecorded::class, 1);
    }

    public function test_nothing_is_announced_while_impersonating(): void
    {
        Event::fake([ActivityRecorded::class]);
        $impersonation = Mockery::mock(ImpersonationContextInterface::class);
        $impersonation->allows('isActive')->andReturn(true);
        $this->app->instance(ImpersonationContextInterface::class, $impersonation);

        $this->publisher()->recorded('forum.post', '7');

        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_nothing_is_announced_inside_the_impersonation_safety_net(): void
    {
        Event::fake([ActivityRecorded::class]);
        // Engaged by hand: engaging it for real would neutralise this test's mail, queue and disks
        (new ReflectionProperty(ImpersonationSafetyNet::class, 'engaged'))->setValue($this->app->make(ImpersonationSafetyNet::class), true);

        $this->publisher()->recorded('forum.post', '7');

        Event::assertNotDispatched(ActivityRecorded::class);
    }

    public function test_an_invalid_type_or_id_is_dropped_with_a_warning_once(): void
    {
        Event::fake([ActivityRecorded::class]);
        $log = Log::spy();
        $publisher = $this->publisher();

        $publisher->recorded('Not a type', '7');
        $publisher->recorded('Not a type', '8');
        $publisher->recorded('forum.post', 'not an id');

        Event::assertNotDispatched(ActivityRecorded::class);
        $log->shouldHaveReceived('warning')->twice();
    }

    public function test_a_listener_that_throws_never_breaks_the_change_that_published(): void
    {
        Event::listen(ActivityRecorded::class, static fn () => throw new RuntimeException('secret@example.test'));
        $log = Log::spy();

        $this->publisher()->recorded('forum.post', '7');

        $log->shouldHaveReceived('warning')->once()->withArgs(
            static fn (string $message, array $context): bool => $context['exception'] === RuntimeException::class
                && ! str_contains(json_encode($context, JSON_THROW_ON_ERROR), 'secret@example.test'),
        );
    }

    private function publisher(): ActivityPublisherInterface
    {
        // Built after any fake, so it dispatches through it
        return $this->app->make(ActivityPublisherInterface::class);
    }
}
