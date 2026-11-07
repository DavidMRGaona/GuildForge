<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Application\Calendar\Services\CalendarFeedTokenServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Calendar\RegistersPersonalCalendarSources;
use Tests\TestCase;

final class CalendarFeedTokenControllerTest extends TestCase
{
    use LazilyRefreshDatabase;
    use RegistersPersonalCalendarSources;

    public function test_issuing_returns_the_private_addresses_once(): void
    {
        $this->registerAttendance([]);
        $user = UserModel::factory()->create();

        $response = $this->actingAs($user)->postJson('/perfil/calendario');

        $response->assertOk();
        $feedUrl = $response->json('feedUrl');
        $this->assertIsString($feedUrl);
        $this->assertMatchesRegularExpression('#^https?://[^/]+/calendario/personal/[A-Za-z0-9_-]{43}\.ics$#', $feedUrl);
        $this->assertSame('webcal://'.preg_replace('#^https?://#', '', $feedUrl), $response->json('webcalUrl'));
        $this->assertIsString($response->json('issuedAt'));

        $token = basename($feedUrl, '.ics');
        $this->assertSame($user->id, app(CalendarFeedTokenServiceInterface::class)->userIdFor($token));
    }

    public function test_issuing_again_regenerates_the_address(): void
    {
        $this->registerAttendance([]);
        $user = UserModel::factory()->create();
        $first = $this->actingAs($user)->postJson('/perfil/calendario')->json('feedUrl');

        $second = $this->actingAs($user)->postJson('/perfil/calendario')->json('feedUrl');

        $this->assertIsString($first);
        $this->assertIsString($second);
        $this->assertNotSame($first, $second);
        $this->assertNull(app(CalendarFeedTokenServiceInterface::class)->userIdFor(basename($first, '.ics')));
    }

    public function test_revoking_removes_the_address(): void
    {
        $this->registerAttendance([]);
        $user = UserModel::factory()->create();
        $feedUrl = $this->actingAs($user)->postJson('/perfil/calendario')->json('feedUrl');
        $this->assertIsString($feedUrl);

        $this->actingAs($user)->deleteJson('/perfil/calendario')->assertNoContent();

        $this->assertNull(app(CalendarFeedTokenServiceInterface::class)->userIdFor(basename($feedUrl, '.ics')));
    }

    public function test_guests_cannot_issue_or_revoke(): void
    {
        $this->registerAttendance([]);

        $this->postJson('/perfil/calendario')->assertUnauthorized();
        $this->deleteJson('/perfil/calendario')->assertUnauthorized();
    }

    public function test_an_account_that_must_verify_its_email_cannot_issue(): void
    {
        $this->registerAttendance([]);
        $this->requireEmailVerification();
        $user = UserModel::factory()->unverified()->create();

        $response = $this->actingAs($user)->postJson('/perfil/calendario');

        $response->assertForbidden();
        $response->assertJson(['error' => 'email_verification_required']);
        $this->assertNull(app(CalendarFeedTokenServiceInterface::class)->issuedAt($user->id));
    }

    public function test_without_attendance_sources_there_is_nothing_to_subscribe_to(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->postJson('/perfil/calendario')->assertNotFound();
    }

    public function test_issuing_is_rate_limited(): void
    {
        $this->registerAttendance([]);
        $user = UserModel::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->postJson('/perfil/calendario')->assertOk();
        }

        $this->actingAs($user)->postJson('/perfil/calendario')->assertTooManyRequests();
    }

    public function test_the_profile_says_whether_a_subscription_exists_without_its_address(): void
    {
        $this->registerAttendance([]);
        $user = UserModel::factory()->create();

        $this->actingAs($user)->get('/perfil')->assertInertia(fn (Assert $page): Assert => $page
            ->where('calendarFeed.available', true)
            ->where('calendarFeed.issuedAt', null));

        $this->actingAs($user)->postJson('/perfil/calendario')->assertOk();

        $this->actingAs($user)->get('/perfil')->assertInertia(function (Assert $page): void {
            $page->where('calendarFeed.available', true);
            $this->assertIsString($page->toArray()['props']['calendarFeed']['issuedAt']);
            $this->assertStringNotContainsString('/calendario/personal/', (string) json_encode($page->toArray()['props']));
        });
    }

    public function test_the_profile_hides_the_subscription_without_attendance_sources(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->get('/perfil')->assertInertia(fn (Assert $page): Assert => $page
            ->where('calendarFeed.available', false));
    }
}
