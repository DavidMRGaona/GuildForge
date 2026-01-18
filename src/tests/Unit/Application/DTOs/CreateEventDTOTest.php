<?php

declare(strict_types=1);

namespace Tests\Unit\Application\DTOs;

use App\Application\DTOs\CreateEventDTO;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CreateEventDTOTest extends TestCase
{
    public function test_it_creates_dto_with_required_data(): void
    {
        $title = 'Warhammer Tournament';
        $description = 'Annual Warhammer 40k tournament.';
        $startDate = new DateTimeImmutable('2024-06-15 10:00:00');

        $dto = new CreateEventDTO(
            title: $title,
            description: $description,
            startDate: $startDate,
        );

        $this->assertEquals($title, $dto->title);
        $this->assertEquals($description, $dto->description);
        $this->assertEquals($startDate, $dto->startDate);
        $this->assertNull($dto->location);
        $this->assertNull($dto->imagePublicId);
        $this->assertNull($dto->endDate);
        $this->assertNull($dto->memberPrice);
        $this->assertNull($dto->nonMemberPrice);
    }

    public function test_it_creates_dto_with_all_data(): void
    {
        $title = 'D&D Campaign';
        $description = 'Weekly campaign session.';
        $startDate = new DateTimeImmutable('2024-07-20 18:00:00');
        $location = 'Association Headquarters';
        $imagePublicId = 'events/dnd-campaign.jpg';

        $dto = new CreateEventDTO(
            title: $title,
            description: $description,
            startDate: $startDate,
            location: $location,
            imagePublicId: $imagePublicId,
        );

        $this->assertEquals($title, $dto->title);
        $this->assertEquals($description, $dto->description);
        $this->assertEquals($startDate, $dto->startDate);
        $this->assertEquals($location, $dto->location);
        $this->assertEquals($imagePublicId, $dto->imagePublicId);
    }

    public function test_it_creates_dto_from_array(): void
    {
        $data = [
            'title' => 'Board Games Night',
            'description' => 'Monthly board games event.',
            'start_date' => '2024-08-10 19:00:00',
            'location' => 'Main Hall',
            'image_public_id' => 'events/board-games.jpg',
        ];

        $dto = CreateEventDTO::fromArray($data);

        $this->assertEquals('Board Games Night', $dto->title);
        $this->assertEquals('Monthly board games event.', $dto->description);
        $this->assertEquals(new DateTimeImmutable('2024-08-10 19:00:00'), $dto->startDate);
        $this->assertEquals('Main Hall', $dto->location);
        $this->assertEquals('events/board-games.jpg', $dto->imagePublicId);
    }

    public function test_it_creates_dto_from_array_with_only_required_fields(): void
    {
        $data = [
            'title' => 'Minimal Event',
            'description' => 'Event with minimal data.',
            'start_date' => '2024-09-01 12:00:00',
        ];

        $dto = CreateEventDTO::fromArray($data);

        $this->assertEquals('Minimal Event', $dto->title);
        $this->assertEquals('Event with minimal data.', $dto->description);
        $this->assertEquals(new DateTimeImmutable('2024-09-01 12:00:00'), $dto->startDate);
        $this->assertNull($dto->location);
        $this->assertNull($dto->imagePublicId);
    }

    public function test_it_creates_dto_with_end_date(): void
    {
        $title = 'Multi-day Convention';
        $description = 'Three-day gaming convention.';
        $startDate = new DateTimeImmutable('2024-10-10 09:00:00');
        $endDate = new DateTimeImmutable('2024-10-12 18:00:00');

        $dto = new CreateEventDTO(
            title: $title,
            description: $description,
            startDate: $startDate,
            endDate: $endDate,
        );

        $this->assertEquals($startDate, $dto->startDate);
        $this->assertEquals($endDate, $dto->endDate);
    }

    public function test_it_creates_dto_with_prices(): void
    {
        $title = 'Tournament with Entry Fee';
        $description = 'Competitive tournament.';
        $startDate = new DateTimeImmutable('2024-11-15 10:00:00');
        $memberPrice = 5.00;
        $nonMemberPrice = 10.00;

        $dto = new CreateEventDTO(
            title: $title,
            description: $description,
            startDate: $startDate,
            memberPrice: $memberPrice,
            nonMemberPrice: $nonMemberPrice,
        );

        $this->assertEquals($memberPrice, $dto->memberPrice);
        $this->assertEquals($nonMemberPrice, $dto->nonMemberPrice);
    }

    public function test_from_array_maps_new_fields(): void
    {
        $data = [
            'title' => 'Premium Event',
            'description' => 'Event with all new fields.',
            'start_date' => '2024-12-01 10:00:00',
            'end_date' => '2024-12-03 18:00:00',
            'member_price' => 15.50,
            'non_member_price' => 25.00,
        ];

        $dto = CreateEventDTO::fromArray($data);

        $this->assertEquals(new DateTimeImmutable('2024-12-01 10:00:00'), $dto->startDate);
        $this->assertEquals(new DateTimeImmutable('2024-12-03 18:00:00'), $dto->endDate);
        $this->assertEquals(15.50, $dto->memberPrice);
        $this->assertEquals(25.00, $dto->nonMemberPrice);
    }
}
