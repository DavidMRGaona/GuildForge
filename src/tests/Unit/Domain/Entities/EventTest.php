<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Entities;

use App\Domain\Entities\Event;
use App\Domain\Exceptions\CannotPublishPastEventException;
use App\Domain\ValueObjects\EventId;
use App\Domain\ValueObjects\Price;
use App\Domain\ValueObjects\Slug;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    public function test_it_creates_event_with_required_data(): void
    {
        $id = EventId::generate();
        $title = 'Warhammer Tournament';
        $slug = new Slug('warhammer-tournament');
        $description = 'Annual Warhammer 40k tournament for all skill levels.';
        $startDate = new DateTimeImmutable('+1 week');

        $event = new Event(
            id: $id,
            title: $title,
            slug: $slug,
            description: $description,
            startDate: $startDate,
        );

        $this->assertEquals($id, $event->id());
        $this->assertEquals($title, $event->title());
        $this->assertEquals($slug, $event->slug());
        $this->assertEquals($description, $event->description());
        $this->assertEquals($startDate, $event->startDate());
        $this->assertNull($event->endDate());
        $this->assertNull($event->location());
        $this->assertNull($event->imagePublicId());
        $this->assertNull($event->memberPrice());
        $this->assertNull($event->nonMemberPrice());
        $this->assertFalse($event->isPublished());
    }

    public function test_it_creates_event_with_all_data(): void
    {
        $id = EventId::generate();
        $title = 'D&D Campaign';
        $slug = new Slug('dnd-campaign');
        $description = 'Weekly Dungeons & Dragons session.';
        $startDate = new DateTimeImmutable('+2 weeks');
        $endDate = new DateTimeImmutable('+2 weeks +3 days');
        $location = 'Association Headquarters';
        $imagePublicId = 'events/dnd-campaign.jpg';
        $memberPrice = new Price(10.0);
        $nonMemberPrice = new Price(15.0);
        $isPublished = true;
        $createdAt = new DateTimeImmutable('2024-01-01 10:00:00');
        $updatedAt = new DateTimeImmutable('2024-01-02 15:30:00');

        $event = new Event(
            id: $id,
            title: $title,
            slug: $slug,
            description: $description,
            startDate: $startDate,
            endDate: $endDate,
            location: $location,
            imagePublicId: $imagePublicId,
            memberPrice: $memberPrice,
            nonMemberPrice: $nonMemberPrice,
            isPublished: $isPublished,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );

        $this->assertEquals($id, $event->id());
        $this->assertEquals($title, $event->title());
        $this->assertEquals($slug, $event->slug());
        $this->assertEquals($description, $event->description());
        $this->assertEquals($startDate, $event->startDate());
        $this->assertEquals($endDate, $event->endDate());
        $this->assertEquals($location, $event->location());
        $this->assertEquals($imagePublicId, $event->imagePublicId());
        $this->assertEquals($memberPrice, $event->memberPrice());
        $this->assertEquals($nonMemberPrice, $event->nonMemberPrice());
        $this->assertTrue($event->isPublished());
        $this->assertEquals($createdAt, $event->createdAt());
        $this->assertEquals($updatedAt, $event->updatedAt());
    }

    public function test_it_creates_event_with_end_date(): void
    {
        $startDate = new DateTimeImmutable('+1 week');
        $endDate = new DateTimeImmutable('+1 week +2 days');

        $event = $this->createEvent(
            startDate: $startDate,
            endDate: $endDate,
        );

        $this->assertEquals($startDate, $event->startDate());
        $this->assertEquals($endDate, $event->endDate());
    }

    public function test_it_detects_multi_day_event(): void
    {
        $event = $this->createEvent(
            startDate: new DateTimeImmutable('2024-06-01 10:00:00'),
            endDate: new DateTimeImmutable('2024-06-03 18:00:00'),
        );

        $this->assertTrue($event->isMultiDay());
    }

    public function test_it_detects_single_day_event(): void
    {
        $event = $this->createEvent(
            startDate: new DateTimeImmutable('2024-06-01 10:00:00'),
        );

        $this->assertFalse($event->isMultiDay());
    }

    public function test_it_creates_event_with_prices(): void
    {
        $memberPrice = new Price(10.0);
        $nonMemberPrice = new Price(15.0);

        $event = $this->createEvent(
            memberPrice: $memberPrice,
            nonMemberPrice: $nonMemberPrice,
        );

        $this->assertEquals($memberPrice, $event->memberPrice());
        $this->assertEquals($nonMemberPrice, $event->nonMemberPrice());
    }

    public function test_it_detects_free_event(): void
    {
        $event = $this->createEvent();

        $this->assertTrue($event->isFree());
    }

    public function test_it_detects_paid_event_with_member_price_only(): void
    {
        $event = $this->createEvent(
            memberPrice: new Price(10.0),
        );

        $this->assertFalse($event->isFree());
    }

    public function test_it_detects_paid_event_with_non_member_price_only(): void
    {
        $event = $this->createEvent(
            nonMemberPrice: new Price(15.0),
        );

        $this->assertFalse($event->isFree());
    }

    public function test_it_detects_paid_event_with_both_prices(): void
    {
        $event = $this->createEvent(
            memberPrice: new Price(10.0),
            nonMemberPrice: new Price(15.0),
        );

        $this->assertFalse($event->isFree());
    }

    public function test_it_is_unpublished_by_default(): void
    {
        $event = $this->createEvent();

        $this->assertFalse($event->isPublished());
    }

    public function test_it_publishes_event(): void
    {
        $event = $this->createEvent();

        $event->publish();

        $this->assertTrue($event->isPublished());
    }

    public function test_it_unpublishes_event(): void
    {
        $event = $this->createEvent(isPublished: true);

        $event->unpublish();

        $this->assertFalse($event->isPublished());
    }

    public function test_it_detects_upcoming_event(): void
    {
        $event = $this->createEvent(
            startDate: new DateTimeImmutable('+1 day')
        );

        $this->assertTrue($event->isUpcoming());
        $this->assertFalse($event->isPast());
    }

    public function test_it_detects_past_event(): void
    {
        $event = $this->createEvent(
            startDate: new DateTimeImmutable('-1 day')
        );

        $this->assertTrue($event->isPast());
        $this->assertFalse($event->isUpcoming());
    }

    public function test_is_past_uses_end_date_when_available(): void
    {
        $event = $this->createEvent(
            startDate: new DateTimeImmutable('-2 days'),
            endDate: new DateTimeImmutable('+2 days'),
        );

        $this->assertFalse($event->isPast());
        $this->assertTrue($event->isUpcoming());
    }

    public function test_it_throws_exception_when_publishing_past_event(): void
    {
        $event = $this->createEvent(
            startDate: new DateTimeImmutable('-1 day')
        );

        $this->expectException(CannotPublishPastEventException::class);

        $event->publish();
    }

    private function createEvent(
        ?DateTimeImmutable $startDate = null,
        ?DateTimeImmutable $endDate = null,
        ?Price $memberPrice = null,
        ?Price $nonMemberPrice = null,
        bool $isPublished = false,
    ): Event {
        return new Event(
            id: EventId::generate(),
            title: 'Test Event',
            slug: new Slug('test-event'),
            description: 'Test event description.',
            startDate: $startDate ?? new DateTimeImmutable('+1 week'),
            endDate: $endDate,
            memberPrice: $memberPrice,
            nonMemberPrice: $nonMemberPrice,
            isPublished: $isPublished,
        );
    }
}
