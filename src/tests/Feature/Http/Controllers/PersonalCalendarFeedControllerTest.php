<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers;

use App\Application\Calendar\DTOs\CalendarEntryReferenceDTO;
use App\Application\Calendar\Services\CalendarFeedTokenServiceInterface;
use App\Application\Services\UserServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\EventModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\Support\Calendar\RegistersPersonalCalendarSources;
use Tests\Support\Time\UsesUtcStorage;
use Tests\TestCase;

final class PersonalCalendarFeedControllerTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RegistersPersonalCalendarSources;
    use UsesUtcStorage;

    protected function setUp(): void
    {
        parent::setUp();

        $this->useUtcStorage();
        $this->travelTo(new DateTimeImmutable('2026-11-07 12:00:00 UTC'));
    }

    public function test_the_feed_lists_only_the_entries_the_person_attends(): void
    {
        $user = UserModel::factory()->create();
        $mine = $this->event('Partida de rol', '2026-11-20 17:00:00');
        $this->event('Torneo ajeno', '2026-11-21 10:00:00');
        $this->registerAttendance([$user->id => [new CalendarEntryReferenceDTO('event', $mine->id)]]);

        $response = $this->get($this->feedUrl($user));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/calendar; charset=utf-8');
        $this->assertStringContainsString("\r\nSUMMARY:Partida de rol\r\n", $response->content());
        $this->assertStringNotContainsString('Torneo ajeno', $response->content());
        $this->assertStringContainsString("\r\nX-WR-CALNAME:", $response->content());
        $this->assertSame(1, substr_count($response->content(), 'BEGIN:VEVENT'));
    }

    public function test_the_feed_is_private_and_not_cached_or_indexed(): void
    {
        $user = UserModel::factory()->create();
        $this->registerAttendance([]);

        $response = $this->get($this->feedUrl($user));

        $response->assertOk();
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function test_the_feed_covers_thirty_days_back_and_twelve_months_ahead(): void
    {
        $user = UserModel::factory()->create();
        $source = $this->registerAttendance([]);

        $this->get($this->feedUrl($user))->assertOk();

        $this->assertNotNull($source->lastFrom);
        $this->assertNotNull($source->lastTo);
        $this->assertSame('2026-10-08T12:00:00+00:00', $source->lastFrom->format(DATE_ATOM));
        $this->assertSame('2027-11-07T12:00:00+00:00', $source->lastTo->format(DATE_ATOM));
    }

    public function test_an_entry_the_public_calendar_hides_is_left_out(): void
    {
        $user = UserModel::factory()->create();
        $draft = EventModel::factory()->draft()->create([
            'title' => 'Borrador secreto',
            'start_date' => '2026-11-20 17:00:00',
            'end_date' => '2026-11-20 20:00:00',
        ]);
        $this->registerAttendance([$user->id => [new CalendarEntryReferenceDTO('event', $draft->id)]]);

        $response = $this->get($this->feedUrl($user));

        $response->assertOk();
        $this->assertStringNotContainsString('Borrador secreto', $response->content());
    }

    public function test_an_unknown_token_is_not_found(): void
    {
        $this->registerAttendance([]);

        $this->get('/calendario/personal/'.str_repeat('a', 43).'.ics')->assertNotFound();
    }

    public function test_a_malformed_token_is_not_found(): void
    {
        $this->get('/calendario/personal/not-a-token.ics')->assertNotFound();
    }

    public function test_a_revoked_token_is_not_found(): void
    {
        $user = UserModel::factory()->create();
        $url = $this->feedUrl($user);
        app(CalendarFeedTokenServiceInterface::class)->revoke($user->id);

        $this->get($url)->assertNotFound();
    }

    public function test_a_regenerated_token_retires_the_previous_address(): void
    {
        $user = UserModel::factory()->create();
        $old = $this->feedUrl($user);
        $new = $this->feedUrl($user);

        $this->get($old)->assertNotFound();
        $this->get($new)->assertOk();
    }

    public function test_a_deactivated_account_is_not_found(): void
    {
        $user = UserModel::factory()->create();
        $url = $this->feedUrl($user);

        $user->delete();

        $this->get($url)->assertNotFound();
    }

    public function test_an_anonymized_account_loses_its_feed(): void
    {
        $user = UserModel::factory()->create();
        $url = $this->feedUrl($user);

        app(UserServiceInterface::class)->anonymize($user->id);

        $this->get($url)->assertNotFound();
        $this->assertNull(app(CalendarFeedTokenServiceInterface::class)->issuedAt($user->id));
    }

    public function test_an_account_that_must_verify_its_email_is_not_found(): void
    {
        $this->requireEmailVerification();
        $user = UserModel::factory()->unverified()->create();

        $this->get($this->feedUrl($user))->assertNotFound();
    }

    public function test_the_feed_is_rate_limited_per_address(): void
    {
        $user = UserModel::factory()->create();
        $url = $this->feedUrl($user);
        $this->registerAttendance([]);

        for ($i = 0; $i < 6; $i++) {
            $this->get($url)->assertOk();
        }

        $this->get($url)->assertTooManyRequests();
    }

    public function test_the_feed_does_not_share_the_limit_of_another_address(): void
    {
        // Calendar services fetch many people's feeds from the same network addresses
        $this->registerAttendance([]);
        $first = $this->feedUrl(UserModel::factory()->create());
        $second = $this->feedUrl(UserModel::factory()->create());

        for ($i = 0; $i < 6; $i++) {
            $this->get($first)->assertOk();
        }

        $this->get($second)->assertOk();
    }

    private function feedUrl(UserModel $user): string
    {
        return '/calendario/personal/'.app(CalendarFeedTokenServiceInterface::class)->issue($user->id).'.ics';
    }

    private function event(string $title, string $start): EventModel
    {
        return EventModel::factory()->published()->create([
            'title' => $title,
            'start_date' => $start,
            'end_date' => (new DateTimeImmutable($start))->modify('+3 hours')->format('Y-m-d H:i:s'),
        ]);
    }
}
