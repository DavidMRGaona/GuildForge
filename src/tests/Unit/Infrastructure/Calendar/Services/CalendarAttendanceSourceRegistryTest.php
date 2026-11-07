<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Calendar\Services;

use App\Infrastructure\Calendar\Services\CalendarAttendanceSourceRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Calendar\FakeCalendarAttendanceSource;
use Tests\Support\Calendar\ThrowingCalendarAttendanceSource;
use Tests\TestCase;

final class CalendarAttendanceSourceRegistryTest extends TestCase
{
    private CalendarAttendanceSourceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new CalendarAttendanceSourceRegistry;
    }

    #[Test]
    public function it_can_register_a_source_class(): void
    {
        $this->registry->register(FakeCalendarAttendanceSource::class);

        $this->assertCount(1, $this->registry->all());
        $this->assertContains(FakeCalendarAttendanceSource::class, $this->registry->all());
    }

    #[Test]
    public function it_can_register_many_source_classes_with_a_module(): void
    {
        $this->registry->registerMany([
            FakeCalendarAttendanceSource::class,
            ThrowingCalendarAttendanceSource::class,
        ], 'game-tables');

        $this->assertCount(2, $this->registry->all());
        $this->assertContains(FakeCalendarAttendanceSource::class, $this->registry->all());
        $this->assertContains(ThrowingCalendarAttendanceSource::class, $this->registry->all());
    }

    #[Test]
    public function it_does_not_duplicate_a_class_registered_twice(): void
    {
        $this->registry->register(FakeCalendarAttendanceSource::class);
        $this->registry->register(FakeCalendarAttendanceSource::class);

        $this->assertCount(1, $this->registry->all());
    }

    #[Test]
    public function it_can_unregister_module_source_classes(): void
    {
        $this->registry->registerMany([FakeCalendarAttendanceSource::class], 'game-tables');
        $this->registry->registerMany([ThrowingCalendarAttendanceSource::class], 'tournaments');

        $this->registry->unregisterModule('game-tables');

        $this->assertCount(1, $this->registry->all());
        $this->assertContains(ThrowingCalendarAttendanceSource::class, $this->registry->all());
        $this->assertNotContains(FakeCalendarAttendanceSource::class, $this->registry->all());
    }

    #[Test]
    public function it_can_clear_all_source_classes(): void
    {
        $this->registry->registerMany([
            FakeCalendarAttendanceSource::class,
            ThrowingCalendarAttendanceSource::class,
        ], 'game-tables');

        $this->registry->clear();

        $this->assertCount(0, $this->registry->all());
        $this->assertSame([], $this->registry->all());
    }
}
