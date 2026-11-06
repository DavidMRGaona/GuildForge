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
     * @return iterable<string, array{string}>
     */
    public static function states(): iterable
    {
        yield 'default' => ['default'];
        yield 'upcoming' => ['upcoming'];
        yield 'past' => ['past'];
    }
}
