<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Calendar\Services;

use App\Infrastructure\Calendar\Services\CalendarSourceRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\Calendar\FakeCalendarSource;
use Tests\Support\Calendar\ThrowingCalendarSource;
use Tests\TestCase;

final class CalendarSourceRegistryTest extends TestCase
{
    private CalendarSourceRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = new CalendarSourceRegistry;
    }

    #[Test]
    public function it_can_register_a_source_class(): void
    {
        $this->registry->register(FakeCalendarSource::class);

        $this->assertCount(1, $this->registry->all());
        $this->assertContains(FakeCalendarSource::class, $this->registry->all());
    }

    #[Test]
    public function it_can_register_many_source_classes_with_a_module(): void
    {
        $this->registry->registerMany([
            FakeCalendarSource::class,
            ThrowingCalendarSource::class,
        ], 'game-tables');

        $this->assertCount(2, $this->registry->all());
        $this->assertContains(FakeCalendarSource::class, $this->registry->all());
        $this->assertContains(ThrowingCalendarSource::class, $this->registry->all());
    }

    #[Test]
    public function it_does_not_duplicate_a_class_registered_twice(): void
    {
        $this->registry->register(FakeCalendarSource::class);
        $this->registry->register(FakeCalendarSource::class);

        $this->assertCount(1, $this->registry->all());
    }

    #[Test]
    public function it_can_unregister_module_source_classes(): void
    {
        $this->registry->registerMany([FakeCalendarSource::class], 'game-tables');
        $this->registry->registerMany([ThrowingCalendarSource::class], 'tournaments');

        $this->registry->unregisterModule('game-tables');

        $this->assertCount(1, $this->registry->all());
        $this->assertContains(ThrowingCalendarSource::class, $this->registry->all());
        $this->assertNotContains(FakeCalendarSource::class, $this->registry->all());
    }

    #[Test]
    public function it_can_clear_all_source_classes(): void
    {
        $this->registry->registerMany([
            FakeCalendarSource::class,
            ThrowingCalendarSource::class,
        ], 'game-tables');

        $this->registry->clear();

        $this->assertCount(0, $this->registry->all());
        $this->assertSame([], $this->registry->all());
    }
}
