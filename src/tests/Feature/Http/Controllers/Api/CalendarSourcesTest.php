<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Application\Calendar\DTOs\CalendarEntryDTO;
use App\Application\Calendar\Services\CalendarSourceRegistryInterface;
use App\Domain\Calendar\Enums\CalendarSourceColor;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Calendar\FakeCalendarSource;
use Tests\Support\Calendar\ThrowingCalendarSource;
use Tests\TestCase;

final class CalendarSourcesTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_endpoint_merges_registered_sources(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Core Event',
            'start_date' => '2025-01-15 10:00:00',
            'end_date' => '2025-01-15 18:00:00',
        ]);

        $this->registerFakeSource([
            $this->makeFakeEntry(
                id: 'fake-1',
                title: 'Fake Entry',
                start: '2025-01-20 09:00:00',
            ),
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $response->assertJsonCount(2);
        $response->assertJsonFragment(['title' => 'Core Event', 'sourceType' => 'event']);
        $response->assertJsonFragment(['title' => 'Fake Entry', 'sourceType' => 'fake']);
    }

    public function test_entries_are_sorted_by_start_ascending(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Middle Event',
            'start_date' => '2025-01-15 10:00:00',
            'end_date' => '2025-01-15 18:00:00',
        ]);

        $this->registerFakeSource([
            $this->makeFakeEntry(
                id: 'fake-early',
                title: 'Early Fake Entry',
                start: '2025-01-05 09:00:00',
            ),
            $this->makeFakeEntry(
                id: 'fake-late',
                title: 'Late Fake Entry',
                start: '2025-01-25 09:00:00',
            ),
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $json = $response->json();

        $this->assertSame(
            ['Early Fake Entry', 'Middle Event', 'Late Fake Entry'],
            array_column($json, 'title'),
        );
    }

    public function test_endpoint_ignores_a_failing_source(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Core Event',
            'start_date' => '2025-01-15 10:00:00',
            'end_date' => '2025-01-15 18:00:00',
        ]);

        app(CalendarSourceRegistryInterface::class)->register(ThrowingCalendarSource::class);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['title' => 'Core Event', 'sourceType' => 'event']);
        $response->assertJsonMissing(['sourceType' => 'throwing']);
    }

    public function test_endpoint_returns_only_core_events_when_no_sources_registered(): void
    {
        EventModel::factory()->published()->create([
            'title' => 'Core Event',
            'start_date' => '2025-01-15 10:00:00',
            'end_date' => '2025-01-15 18:00:00',
        ]);

        $response = $this->getJson('/eventos/calendario?start=2025-01-01&end=2025-01-31');

        $response->assertStatus(200);
        $response->assertJsonCount(1);
        $json = $response->json();

        $this->assertSame(['event'], array_unique(array_column($json, 'sourceType')));
    }

    /**
     * @param  array<CalendarEntryDTO>  $entries
     */
    private function registerFakeSource(array $entries): void
    {
        app()->instance(FakeCalendarSource::class, new FakeCalendarSource($entries));
        app(CalendarSourceRegistryInterface::class)->register(FakeCalendarSource::class);
    }

    private function makeFakeEntry(string $id, string $title, string $start): CalendarEntryDTO
    {
        return new CalendarEntryDTO(
            id: $id,
            sourceType: 'fake',
            sourceLabel: 'Fake source',
            color: CalendarSourceColor::Accent,
            title: $title,
            start: new DateTimeImmutable($start),
            end: null,
            url: '/fake/'.$id,
            details: [],
        );
    }
}
