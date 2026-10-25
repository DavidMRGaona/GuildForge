<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\Persistence;

use App\Domain\Entities\Event;
use App\Domain\Repositories\EventRepositoryInterface;
use App\Domain\ValueObjects\EventId;
use App\Domain\ValueObjects\Slug;
use App\Infrastructure\Persistence\Eloquent\Concerns\StoresInstantsInStorageTimezone;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\SesUsageRecordModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Time\StorageTimezoneResolver;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

final class StoresInstantsInStorageTimezoneTest extends TestCase
{
    use RefreshDatabase;
    use UsesUtcStorage;

    public function test_an_instant_in_another_zone_is_stored_as_storage_wall_time(): void
    {
        // This test is about Madrid storage, whatever the suite default is
        config(['app.storage_timezone_default' => 'Europe/Madrid']);
        (new StorageTimezoneResolver)->apply($this->app);

        $event = EventModel::factory()->create([
            'start_date' => new DateTimeImmutable('2026-10-25T03:30:00', new DateTimeZone('UTC')),
            'end_date' => new DateTimeImmutable('2026-10-25T07:30:00', new DateTimeZone('UTC')),
        ]);

        // Storage is Europe/Madrid while no marker exists: 03:30Z is 04:30 CET
        $this->assertSame('2026-10-25 04:30:00', DB::table('events')->where('id', $event->id)->value('start_date'));
    }

    public function test_with_utc_storage_a_madrid_instant_is_stored_in_utc(): void
    {
        $this->useUtcStorage();

        $event = EventModel::factory()->create([
            'start_date' => new DateTimeImmutable('2026-10-25 03:30', new DateTimeZone('Europe/Madrid')),
            'end_date' => new DateTimeImmutable('2026-10-25 07:30', new DateTimeZone('Europe/Madrid')),
        ]);

        $this->assertSame('2026-10-25 02:30:00', DB::table('events')->where('id', $event->id)->value('start_date'));
    }

    public function test_the_event_repository_stores_an_entity_start_in_the_storage_zone(): void
    {
        $this->useUtcStorage();

        $madrid = new DateTimeZone('Europe/Madrid');
        $id = EventId::generate();
        $event = new Event(
            id: $id,
            title: 'Summer meetup',
            slug: new Slug('summer-meetup'),
            description: 'A summer meetup.',
            startDate: new DateTimeImmutable('2026-07-01 12:00', $madrid),
            endDate: new DateTimeImmutable('2026-07-01 14:00', $madrid),
        );

        app(EventRepositoryInterface::class)->save($event);

        $row = DB::table('events')->where('id', $id->value)->first();
        $this->assertNotNull($row);
        $this->assertSame('2026-07-01 10:00:00', $row->start_date);
        $this->assertSame('2026-07-01 12:00:00', $row->end_date);
    }

    public function test_a_date_cast_keeps_its_calendar_day_under_utc_storage(): void
    {
        $this->useUtcStorage();

        $record = new SesUsageRecordModel;
        $record->setAttribute('date', new DateTimeImmutable('2026-10-25 00:00', new DateTimeZone('Europe/Madrid')));

        $this->assertSame('2026-10-25 00:00:00', $record->getAttributes()['date']);
    }

    public function test_an_instant_attribute_is_still_converted_under_utc_storage(): void
    {
        $this->useUtcStorage();

        $event = new EventModel;
        $event->setAttribute('start_date', new DateTimeImmutable('2026-10-25 00:30', new DateTimeZone('Europe/Madrid')));

        $this->assertSame('2026-10-24 22:30:00', $event->getAttributes()['start_date']);
    }

    public function test_a_string_with_an_offset_is_converted_to_the_storage_zone(): void
    {
        $this->useUtcStorage();

        $event = new EventModel;
        $event->setAttribute('start_date', '2026-07-01T12:00:00+02:00');

        $this->assertSame('2026-07-01 10:00:00', $event->getAttributes()['start_date']);
    }

    public function test_a_string_without_offset_is_kept_as_a_storage_wall_time(): void
    {
        $this->useUtcStorage();

        $event = new EventModel;
        $event->setAttribute('start_date', '2026-07-01 12:00:00');

        $this->assertSame('2026-07-01 12:00:00', $event->getAttributes()['start_date']);
    }

    public function test_a_date_cast_string_keeps_its_calendar_day_under_utc_storage(): void
    {
        $this->useUtcStorage();

        $record = new SesUsageRecordModel;
        $record->setAttribute('date', '2026-10-25');

        $this->assertSame('2026-10-25 00:00:00', $record->getAttributes()['date']);
    }

    public function test_a_date_cast_offset_string_keeps_its_calendar_day(): void
    {
        $this->useUtcStorage();

        $record = new SesUsageRecordModel;
        $record->setAttribute('date', '2026-10-25T00:30:00+02:00');

        $this->assertSame('2026-10-25 00:00:00', $record->getAttributes()['date']);
    }

    public function test_a_custom_date_cast_keeps_its_calendar_day(): void
    {
        $this->useUtcStorage();

        $model = new CustomDateCastModel;
        $model->setAttribute('day', new DateTimeImmutable('2026-10-25 00:00', new DateTimeZone('Europe/Madrid')));
        $this->assertStringStartsWith('2026-10-25', $model->getAttributes()['day']);

        $model->setAttribute('day', '2026-10-25T00:30:00+02:00');
        $this->assertStringStartsWith('2026-10-25', $model->getAttributes()['day']);

        $model->setAttribute('imm', new DateTimeImmutable('2026-10-25 00:00', new DateTimeZone('Europe/Madrid')));
        $this->assertStringStartsWith('2026-10-25', $model->getAttributes()['imm']);
    }

    public function test_null_and_plain_strings_pass_through_unchanged(): void
    {
        $this->useUtcStorage();

        $event = new EventModel;
        $event->setAttribute('start_date', '2026-07-01 12:00:00');
        $event->setAttribute('end_date', null);

        $this->assertSame('2026-07-01 12:00:00', $event->getAttributes()['start_date']);
        $this->assertNull($event->getAttributes()['end_date']);
    }

    public function test_a_mutable_carbon_passed_in_is_not_mutated(): void
    {
        $this->useUtcStorage();

        $moment = Carbon::parse('2026-10-25 00:30', 'Europe/Madrid');
        $event = new EventModel;
        $event->setAttribute('start_date', $moment);

        $this->assertSame('Europe/Madrid', $moment->getTimezone()->getName());
        $this->assertSame('2026-10-25 00:30:00', $moment->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-24 22:30:00', $event->getAttributes()['start_date']);
    }

    public function test_timestamps_and_soft_deletes_are_stored_in_utc(): void
    {
        $this->useUtcStorage();
        $this->travelTo(Carbon::parse('2026-10-25 12:30:00', 'UTC'));

        $event = EventModel::factory()->create();
        $this->assertSame('2026-10-25 12:30:00', DB::table('events')->where('id', $event->id)->value('created_at'));
        $this->assertSame('2026-10-25 12:30:00', DB::table('events')->where('id', $event->id)->value('updated_at'));

        $user = UserModel::factory()->create();
        $user->delete();
        $this->assertSame('2026-10-25 12:30:00', DB::table('users')->where('id', $user->id)->value('deleted_at'));
    }

    public function test_a_relative_date_string_follows_the_test_clock(): void
    {
        $this->useUtcStorage();
        $this->travelTo(Carbon::parse('2031-03-04 12:00:00', 'UTC'));

        $record = new SesUsageRecordModel;
        $record->setAttribute('date', 'today');

        $this->assertSame('2031-03-04 00:00:00', $record->getAttributes()['date']);
    }

    public function test_a_custom_datetime_cast_converts_a_string_with_an_offset(): void
    {
        $this->useUtcStorage();

        $model = new CustomDateCastModel;
        $model->setAttribute('moment', '2026-07-01T12:00:00+02:00');

        $this->assertSame('2026-07-01 10:00', Carbon::parse($model->getAttributes()['moment'])->format('Y-m-d H:i'));
    }

    public function test_a_custom_datetime_cast_is_still_converted_as_an_instant(): void
    {
        $this->useUtcStorage();

        $model = new CustomDateCastModel;
        $model->setAttribute('moment', new DateTimeImmutable('2026-10-25 00:30', new DateTimeZone('Europe/Madrid')));

        $this->assertSame('2026-10-24 22:30', $model->getAttributes()['moment']->format('Y-m-d H:i'));
    }
}

final class CustomDateCastModel extends Model
{
    use StoresInstantsInStorageTimezone;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day' => 'date:Y-m-d',
            'imm' => 'immutable_date:Y-m-d',
            'moment' => 'datetime:Y-m-d H:i',
        ];
    }
}
