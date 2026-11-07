<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Api;

use App\Application\Calendar\DTOs\CalendarEntryReferenceDTO;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Calendar\RegistersPersonalCalendarSources;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

final class PersonalCalendarFilterTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RegistersPersonalCalendarSources;
    use UsesUtcStorage;

    private const string RANGE = 'start=2026-11-01&end=2026-12-01';

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
    }

    public function test_only_mine_keeps_the_entries_the_person_attends(): void
    {
        $user = UserModel::factory()->create();
        $mine = $this->event('Mi partida', '2026-11-20 17:00:00');
        $this->event('Otra partida', '2026-11-21 17:00:00');
        $this->registerAttendance([$user->id => [new CalendarEntryReferenceDTO('event', $mine->id)]]);

        $response = $this->actingAs($user)->getJson('/eventos/calendario?'.self::RANGE.'&mine=1');

        $response->assertOk();
        $response->assertJsonCount(1);
        $response->assertJsonFragment(['title' => 'Mi partida']);
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
    }

    public function test_without_the_flag_everyone_gets_the_whole_calendar(): void
    {
        $user = UserModel::factory()->create();
        $this->event('Mi partida', '2026-11-20 17:00:00');
        $this->event('Otra partida', '2026-11-21 17:00:00');
        $this->registerAttendance([]);

        $this->actingAs($user)->getJson('/eventos/calendario?'.self::RANGE)->assertOk()->assertJsonCount(2);
        $this->getJson('/eventos/calendario?'.self::RANGE.'&mine=0')->assertOk()->assertJsonCount(2);
    }

    public function test_guests_cannot_ask_for_their_own_calendar(): void
    {
        $this->registerAttendance([]);

        $this->getJson('/eventos/calendario?'.self::RANGE.'&mine=1')->assertUnauthorized();
    }

    public function test_an_account_that_must_verify_its_email_gets_the_verification_error(): void
    {
        $this->registerAttendance([]);
        $this->requireEmailVerification();
        $user = UserModel::factory()->unverified()->create();

        $response = $this->actingAs($user)->getJson('/eventos/calendario?'.self::RANGE.'&mine=1');

        $response->assertForbidden();
        $response->assertJson(['error' => 'email_verification_required']);
        $response->assertHeader('X-Email-Verification-Required', '1');
    }

    public function test_the_whole_calendar_stays_open_to_accounts_that_must_verify(): void
    {
        $this->requireEmailVerification();
        $this->event('Mi partida', '2026-11-20 17:00:00');

        $this->actingAs(UserModel::factory()->unverified()->create())
            ->getJson('/eventos/calendario?'.self::RANGE)
            ->assertOk()
            ->assertJsonCount(1);
    }

    public function test_the_flag_must_be_a_boolean(): void
    {
        $this->actingAs(UserModel::factory()->create())
            ->getJson('/eventos/calendario?'.self::RANGE.'&mine=everything')
            ->assertUnprocessable();
    }

    public function test_the_calendar_page_offers_the_filter_to_signed_in_people(): void
    {
        $this->registerAttendance([]);

        $this->actingAs(UserModel::factory()->create())->get('/calendario')
            ->assertInertia(fn (Assert $page): Assert => $page->where('personalCalendar', true));
    }

    public function test_the_calendar_page_never_offers_the_filter_to_guests(): void
    {
        $this->registerAttendance([]);

        $this->get('/calendario')->assertInertia(fn (Assert $page): Assert => $page->where('personalCalendar', false));
    }

    public function test_the_calendar_page_hides_the_filter_without_attendance_sources(): void
    {
        $this->actingAs(UserModel::factory()->create())->get('/calendario')
            ->assertInertia(fn (Assert $page): Assert => $page->where('personalCalendar', false));
    }

    public function test_the_calendar_page_hides_the_filter_while_the_email_must_be_verified(): void
    {
        $this->registerAttendance([]);
        $this->requireEmailVerification();

        $this->actingAs(UserModel::factory()->unverified()->create())->get('/calendario')
            ->assertInertia(fn (Assert $page): Assert => $page->where('personalCalendar', false));
    }

    private function event(string $title, string $start): EventModel
    {
        return EventModel::factory()->published()->create([
            'title' => $title,
            'start_date' => $start,
            'end_date' => date('Y-m-d H:i:s', (int) strtotime($start.' +3 hours')),
        ]);
    }
}
