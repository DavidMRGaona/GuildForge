<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence\Eloquent\Models;

use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_event_in_database(): void
    {
        $event = EventModel::factory()->create([
            'title' => 'Warhammer Tournament',
            'slug' => 'warhammer-tournament',
            'description' => 'Annual tournament for all players.',
            'start_date' => '2024-06-15 10:00:00',
            'end_date' => '2024-06-15 18:00:00',
            'location' => 'Main Hall',
            'is_published' => true,
        ]);

        $this->assertDatabaseHas('events', [
            'id' => $event->id,
            'title' => 'Warhammer Tournament',
            'slug' => 'warhammer-tournament',
            'is_published' => true,
        ]);
    }

    public function test_it_has_correct_fillable_attributes(): void
    {
        $fillable = [
            'id',
            'title',
            'slug',
            'description',
            'start_date',
            'end_date',
            'location',
            'member_price',
            'non_member_price',
            'image_public_id',
            'is_published',
        ];

        $model = new EventModel();

        $this->assertEquals($fillable, $model->getFillable());
    }

    public function test_it_casts_dates_correctly(): void
    {
        $event = EventModel::factory()->create([
            'start_date' => '2024-06-15 10:00:00',
        ]);

        $this->assertInstanceOf(\DateTimeInterface::class, $event->start_date);
        $this->assertEquals('2024-06-15', $event->start_date->format('Y-m-d'));
    }

    public function test_it_casts_end_date_correctly(): void
    {
        $event = EventModel::factory()->create([
            'start_date' => '2024-06-15 10:00:00',
            'end_date' => '2024-06-16 18:00:00',
        ]);

        $this->assertInstanceOf(\DateTimeInterface::class, $event->end_date);
        $this->assertEquals('2024-06-16', $event->end_date->format('Y-m-d'));
    }

    public function test_it_casts_decimal_prices_correctly(): void
    {
        $event = EventModel::factory()->create([
            'member_price' => '15.50',
            'non_member_price' => '20.00',
        ]);

        $this->assertIsString($event->member_price);
        $this->assertIsString($event->non_member_price);
        $this->assertEquals('15.50', $event->member_price);
        $this->assertEquals('20.00', $event->non_member_price);
    }

    public function test_it_casts_boolean_correctly(): void
    {
        $event = EventModel::factory()->create([
            'is_published' => true,
        ]);

        $this->assertTrue($event->is_published);
        $this->assertIsBool($event->is_published);
    }

    public function test_factory_creates_draft_event_by_default(): void
    {
        $event = EventModel::factory()->create();

        $this->assertFalse($event->is_published);
    }

    public function test_factory_published_state_creates_published_event(): void
    {
        $event = EventModel::factory()->published()->create();

        $this->assertTrue($event->is_published);
    }

    public function test_factory_multiday_state_creates_event_with_end_date(): void
    {
        $event = EventModel::factory()->multiDay()->create();

        $this->assertNotNull($event->end_date);
        $this->assertInstanceOf(\DateTimeInterface::class, $event->end_date);
        $this->assertGreaterThan($event->start_date, $event->end_date);
    }

    public function test_factory_upcoming_state_creates_future_event(): void
    {
        $event = EventModel::factory()->upcoming()->create();

        $this->assertGreaterThan(now(), $event->start_date);
    }

    public function test_factory_past_state_creates_past_event(): void
    {
        $event = EventModel::factory()->past()->create();

        $this->assertLessThan(now(), $event->start_date);
    }
}
