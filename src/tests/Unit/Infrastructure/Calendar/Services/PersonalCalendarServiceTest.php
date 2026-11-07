<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Calendar\Services;

use App\Application\Calendar\Contracts\CalendarAttendanceSourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\DTOs\CalendarEntryReferenceDTO;
use App\Application\Calendar\Services\CalendarAggregatorServiceInterface;
use App\Application\Calendar\Services\CalendarAttendanceSourceRegistryInterface;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use App\Infrastructure\Calendar\Services\CalendarAttendanceSourceRegistry;
use App\Infrastructure\Calendar\Services\PersonalCalendarService;
use DateTimeImmutable;
use Illuminate\Support\Facades\Log;
use Mockery;
use Tests\Support\Calendar\FakeCalendarAttendanceSource;
use Tests\Support\Calendar\ThrowingCalendarAttendanceSource;
use Tests\TestCase;

final class PersonalCalendarServiceTest extends TestCase
{
    private const string USER = '0198a1b2-0000-7000-8000-000000000001';

    private CalendarAttendanceSourceRegistryInterface $registry;

    /** @var array<CalendarEntryDTO> */
    private array $publicEntries = [];

    /** @var array<array{DateTimeImmutable, DateTimeImmutable}> */
    private array $aggregatorCalls = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new CalendarAttendanceSourceRegistry;
    }

    public function test_it_is_not_available_without_attendance_sources(): void
    {
        $this->assertFalse($this->service()->isAvailable());
    }

    public function test_it_is_available_with_an_attendance_source(): void
    {
        $this->registerSource('first', new FakeCalendarAttendanceSource);

        $this->assertTrue($this->service()->isAvailable());
    }

    public function test_it_keeps_only_the_public_entries_the_person_attends(): void
    {
        $this->publicEntries = [
            $this->entry('event', 'e1', '2026-11-10 10:00:00'),
            $this->entry('event', 'e2', '2026-11-11 10:00:00'),
            $this->entry('game-table', 't1', '2026-11-12 10:00:00'),
        ];
        $this->registerSource('first', new FakeCalendarAttendanceSource([
            self::USER => [new CalendarEntryReferenceDTO('event', 'e2'), new CalendarEntryReferenceDTO('game-table', 't1')],
            'someone-else' => [new CalendarEntryReferenceDTO('event', 'e1')],
        ]));

        $entries = $this->service()->entriesFor(self::USER, ...$this->range());

        $this->assertSame(['e2', 't1'], array_map(static fn (CalendarEntryDTO $entry): string => $entry->id, $entries));
    }

    public function test_a_reference_to_an_entry_of_another_source_is_kept(): void
    {
        // A tournament participant attends the core event the tournament belongs to
        $this->publicEntries = [$this->entry('event', 'e1', '2026-11-10 10:00:00')];
        $this->registerSource('tournaments', new FakeCalendarAttendanceSource([
            self::USER => [new CalendarEntryReferenceDTO('event', 'e1')],
        ]));

        $this->assertCount(1, $this->service()->entriesFor(self::USER, ...$this->range()));
    }

    public function test_references_the_public_calendar_does_not_show_are_dropped(): void
    {
        $this->publicEntries = [$this->entry('event', 'e1', '2026-11-10 10:00:00')];
        $this->registerSource('first', new FakeCalendarAttendanceSource([
            self::USER => [
                new CalendarEntryReferenceDTO('event', 'hidden'),
                new CalendarEntryReferenceDTO('unknown-source', 'e1'),
            ],
        ]));

        $this->assertSame([], $this->service()->entriesFor(self::USER, ...$this->range()));
    }

    public function test_an_entry_referenced_by_two_sources_appears_once(): void
    {
        $this->publicEntries = [$this->entry('event', 'e1', '2026-11-10 10:00:00')];
        $references = [self::USER => [new CalendarEntryReferenceDTO('event', 'e1')]];
        $this->registerSource('first', new FakeCalendarAttendanceSource($references));
        $this->registerSource('second', new FakeCalendarAttendanceSource($references), 'other-module');

        $this->assertCount(1, $this->service()->entriesFor(self::USER, ...$this->range()));
    }

    public function test_entries_keep_the_public_calendar_order(): void
    {
        $this->publicEntries = [
            $this->entry('event', 'early', '2026-11-05 10:00:00'),
            $this->entry('event', 'late', '2026-11-25 10:00:00'),
        ];
        $this->registerSource('first', new FakeCalendarAttendanceSource([
            self::USER => [new CalendarEntryReferenceDTO('event', 'late'), new CalendarEntryReferenceDTO('event', 'early')],
        ]));

        $entries = $this->service()->entriesFor(self::USER, ...$this->range());

        $this->assertSame(['early', 'late'], array_map(static fn (CalendarEntryDTO $entry): string => $entry->id, $entries));
    }

    public function test_a_failing_source_is_skipped_and_logged(): void
    {
        $log = Log::spy();
        $this->publicEntries = [$this->entry('event', 'e1', '2026-11-10 10:00:00')];
        $this->registry->register(ThrowingCalendarAttendanceSource::class, 'broken');
        $this->registerSource('first', new FakeCalendarAttendanceSource([
            self::USER => [new CalendarEntryReferenceDTO('event', 'e1')],
        ]));

        $this->assertCount(1, $this->service()->entriesFor(self::USER, ...$this->range()));
        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message, array $context): bool => str_contains($message, 'attendance source failed')
                && $context['source'] === ThrowingCalendarAttendanceSource::class
        )->once();
    }

    public function test_a_source_that_cannot_be_resolved_is_skipped_and_logged(): void
    {
        $log = Log::spy();
        // An interface cannot be built, like the class of a module whose code is gone
        $this->registry->register(CalendarAttendanceSourceInterface::class, 'missing');

        $this->assertSame([], $this->service()->entriesFor(self::USER, ...$this->range()));
        $log->shouldHaveReceived('warning')->withArgs(
            static fn (string $message): bool => str_contains($message, 'Failed to resolve calendar attendance source')
        )->once();
    }

    public function test_anything_that_is_not_a_reference_is_ignored(): void
    {
        $this->publicEntries = [$this->entry('event', 'e1', '2026-11-10 10:00:00')];
        // A module returning the wrong shape must not leak or break anything
        $source = Mockery::mock(CalendarAttendanceSourceInterface::class);
        $source->shouldReceive('attendedEntries')->andReturn([['sourceType' => 'event', 'id' => 'e1'], 'event:e1']);
        $this->registerSource('wrongShape', $source);

        $this->assertSame([], $this->service()->entriesFor(self::USER, ...$this->range()));
    }

    public function test_without_references_the_public_calendar_is_not_read(): void
    {
        $this->registerSource('first', new FakeCalendarAttendanceSource);

        $this->assertSame([], $this->service()->entriesFor(self::USER, ...$this->range()));
        $this->assertSame([], $this->aggregatorCalls);
    }

    public function test_sources_and_the_public_calendar_get_the_same_range(): void
    {
        $source = new FakeCalendarAttendanceSource([self::USER => [new CalendarEntryReferenceDTO('event', 'e1')]]);
        $this->registerSource('first', $source);
        [$from, $to] = $this->range();

        $this->service()->entriesFor(self::USER, $from, $to);

        $this->assertEquals($from, $source->lastFrom);
        $this->assertEquals($to, $source->lastTo);
        $this->assertEquals([[$from, $to]], $this->aggregatorCalls);
    }

    private function service(): PersonalCalendarService
    {
        return new PersonalCalendarService($this->app, $this->registry, $this->aggregator());
    }

    private function registerSource(string $name, CalendarAttendanceSourceInterface $source, ?string $module = 'example-module'): void
    {
        // Distinct container bindings so several fake sources can be registered at once
        $class = 'Tests\\Support\\Calendar\\Bound\\'.ucfirst($name).'AttendanceSource';
        $this->app->instance($class, $source);
        /** @var class-string<CalendarAttendanceSourceInterface> $class */
        $this->registry->register($class, $module);
    }

    private function aggregator(): CalendarAggregatorServiceInterface
    {
        $test = $this;

        return new readonly class($test) implements CalendarAggregatorServiceInterface
        {
            public function __construct(private PersonalCalendarServiceTest $test) {}

            public function findByDateRange(DateTimeImmutable $from, DateTimeImmutable $to): array
            {
                return $this->test->publicEntriesFor($from, $to);
            }

            public function findEntry(string $sourceType, string $id): ?CalendarEntryDTO
            {
                return null;
            }

            public function canFindEntries(string $sourceType): bool
            {
                return false;
            }
        };
    }

    /**
     * @return array<CalendarEntryDTO>
     *
     * @internal Called by the aggregator double.
     */
    public function publicEntriesFor(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $this->aggregatorCalls[] = [$from, $to];

        return $this->publicEntries;
    }

    /**
     * @return array{DateTimeImmutable, DateTimeImmutable}
     */
    private function range(): array
    {
        return [new DateTimeImmutable('2026-11-01 00:00:00'), new DateTimeImmutable('2026-12-01 00:00:00')];
    }

    private function entry(string $sourceType, string $id, string $start): CalendarEntryDTO
    {
        return new CalendarEntryDTO(
            id: $id,
            sourceType: $sourceType,
            sourceLabel: $sourceType,
            color: CalendarSourceColor::Primary,
            title: 'Entry '.$id,
            start: new DateTimeImmutable($start),
            end: null,
            url: '/entries/'.$id,
        );
    }
}
