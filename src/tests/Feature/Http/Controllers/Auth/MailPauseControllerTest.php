<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Application\Services\SettingsServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class MailPauseControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_a_guest_cannot_pause(): void
    {
        $this->put('/perfil/correos/pausa', ['duration' => 'week'])->assertRedirect();

        $this->assertSame(0, DB::table('user_mail_pauses')->count());
    }

    public function test_it_pauses_for_a_week_and_the_tab_says_until_when(): void
    {
        $this->travelTo('2026-12-01 10:00:00');
        $user = UserModel::factory()->create();

        $this->actingAs($user)
            ->put('/perfil/correos/pausa', ['duration' => 'week'])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)->get('/perfil?tab=mail')
            ->assertInertia(fn (Assert $page) => $page
                ->where('mailPreferences.pause.active', true)
                ->where('mailPreferences.pause.until', '2026-12-09T00:00:00+01:00')
                ->where('mailPreferences.pause.lastDay', '2026-12-08'));
    }

    public function test_an_indefinite_pause_has_no_end_date(): void
    {
        $user = UserModel::factory()->create();

        $this->actingAs($user)->put('/perfil/correos/pausa', ['duration' => 'indefinite'])->assertSessionHas('success');

        $this->actingAs($user)->get('/perfil?tab=mail')
            ->assertInertia(fn (Assert $page) => $page
                ->where('mailPreferences.pause.active', true)
                ->where('mailPreferences.pause.until', null)
                ->where('mailPreferences.pause.lastDay', null));
    }

    public function test_without_a_pause_the_tab_says_so(): void
    {
        $this->actingAs(UserModel::factory()->create())->get('/perfil?tab=mail')
            ->assertInertia(fn (Assert $page) => $page
                ->where('mailPreferences.pause.active', false)
                ->where('mailPreferences.pause.until', null));
    }

    public function test_an_expired_pause_shows_as_not_active(): void
    {
        $this->travelTo('2026-12-01 10:00:00');
        $user = UserModel::factory()->create();
        $this->actingAs($user)->put('/perfil/correos/pausa', ['duration' => 'week']);

        $this->travelTo('2026-12-11 10:00:00');

        $this->actingAs($user)->get('/perfil?tab=mail')
            ->assertInertia(fn (Assert $page) => $page->where('mailPreferences.pause.active', false));
    }

    public function test_it_rejects_a_duration_it_does_not_offer(): void
    {
        $user = UserModel::factory()->create();

        foreach (['year', '', 'Week', 7] as $duration) {
            $this->actingAs($user)->put('/perfil/correos/pausa', ['duration' => $duration])
                ->assertSessionHasErrors('duration');
        }
        $this->actingAs($user)->put('/perfil/correos/pausa', [])->assertSessionHasErrors('duration');

        $this->assertSame(0, DB::table('user_mail_pauses')->count());
    }

    public function test_it_resumes(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->actingAs($user)->put('/perfil/correos/pausa', ['duration' => 'indefinite']);
        $this->actingAs($other)->put('/perfil/correos/pausa', ['duration' => 'indefinite']);

        $this->actingAs($user)->delete('/perfil/correos/pausa')->assertRedirect()->assertSessionHas('success');

        $this->assertSame([$other->id], DB::table('user_mail_pauses')->pluck('user_id')->all());
    }

    public function test_resuming_without_a_pause_is_harmless(): void
    {
        $this->actingAs(UserModel::factory()->create())->delete('/perfil/correos/pausa')->assertRedirect();
    }

    public function test_an_anonymized_account_cannot_pause(): void
    {
        $user = UserModel::factory()->create(['anonymized_at' => now()]);

        $this->actingAs($user)->put('/perfil/correos/pausa', ['duration' => 'week'])->assertForbidden();

        $this->assertSame(0, DB::table('user_mail_pauses')->count());
    }

    public function test_an_account_that_must_verify_its_email_is_sent_to_the_notice(): void
    {
        $settings = app(SettingsServiceInterface::class);
        $settings->set('auth_email_verification_required', '1');
        $settings->set('email_verification_enforced_since', '2000-01-01T00:00:00+00:00');
        $user = UserModel::factory()->unverified()->create();

        $this->actingAs($user)
            ->put('/perfil/correos/pausa', ['duration' => 'week'])
            ->assertRedirect(route('verification.notice'));

        $this->assertSame(0, DB::table('user_mail_pauses')->count());
    }
}
