<?php

declare(strict_types=1);

namespace Tests\Integration\Database\Factories;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class EventModelFactoryTest extends TestCase
{
    /**
     * The panel form rejects an end equal to the start, so a factory event sent
     * through it must always end after it starts.
     */
    #[DataProvider('states')]
    public function test_events_always_end_after_they_start(string $state): void
    {
        for ($i = 0; $i < 100; $i++) {
            $factory = EventModel::factory();
            $event = $state === 'default' ? $factory->make() : $factory->{$state}()->make();

            $this->assertGreaterThan(
                $event->start_date->getTimestamp(),
                $event->end_date->getTimestamp(),
                "The '{$state}' state gave an event ending when it starts.",
            );
        }
    }

    /**
     * A "past" event must have ended, however the factory's randomness falls:
     * the start is drawn far enough back that the longest end still lies behind us.
     */
    #[DataProvider('pastStates')]
    public function test_past_events_have_always_ended(string $state): void
    {
        $now = now();

        for ($i = 0; $i < 300; $i++) {
            $factory = EventModel::factory()->published()->past();
            $event = $state === 'past' ? $factory->make() : $factory->multiDay()->make();

            $this->assertLessThan(
                $now->getTimestamp(),
                $event->end_date->getTimestamp(),
                "The '{$state}' state gave an event that has not ended yet (start {$event->start_date}, end {$event->end_date}).",
            );
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function pastStates(): iterable
    {
        yield 'past' => ['past'];
        yield 'past multi-day' => ['past multi-day'];
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function states(): iterable
    {
        yield 'default' => ['default'];
        yield 'upcoming' => ['upcoming'];
        yield 'past' => ['past'];
    }
}
