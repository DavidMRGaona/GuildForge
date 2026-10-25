<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Notifications\VerifyPendingEmailNotification;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

final class EmailVerificationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_the_notice_renders_for_an_unverified_account(): void
    {
        $this->actingAs(UserModel::factory()->unverified()->create());

        $this->get(route('verification.notice'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/VerifyEmail'));
    }

    public function test_the_notice_says_where_the_link_was_sent(): void
    {
        $this->actingAs(UserModel::factory()->unverified()->create(['email' => 'current@example.com']));

        $this->get(route('verification.notice'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Auth/VerifyEmail')
                ->where('email', 'current@example.com')
                ->where('pendingEmail', null));
    }

    public function test_the_notice_shows_the_pending_email(): void
    {
        $this->actingAs(UserModel::factory()->unverified()->create([
            'email' => 'typo@example.com',
            'pending_email' => 'right@example.com',
        ]));

        $this->get(route('verification.notice'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('email', 'typo@example.com')
                ->where('pendingEmail', 'right@example.com'));
    }

    public function test_sending_again_goes_to_the_current_email_without_a_pending_one(): void
    {
        Notification::fake();
        $user = UserModel::factory()->unverified()->create();
        $this->actingAs($user);

        $this->post(route('verification.send'))->assertSessionHas('success');

        Notification::assertSentTo($user, VerifyEmail::class);
        Notification::assertNotSentTo($user, VerifyPendingEmailNotification::class);
    }

    public function test_sending_again_confirms_the_pending_email_when_there_is_one(): void
    {
        Notification::fake();
        $user = UserModel::factory()->unverified()->create([
            'email' => 'typo@example.com',
            'pending_email' => 'right@example.com',
        ]);
        $this->actingAs($user);

        $this->post(route('verification.send'))->assertSessionHas('success');

        Notification::assertSentTo(
            $user,
            VerifyPendingEmailNotification::class,
            static fn (VerifyPendingEmailNotification $notification): bool => $notification->mailRecipient() === 'right@example.com',
        );
        Notification::assertNotSentTo($user, VerifyEmail::class);
    }

    public function test_a_verification_link_for_another_account_is_rejected(): void
    {
        $signedIn = UserModel::factory()->unverified()->create();
        $other = UserModel::factory()->unverified()->create();
        $this->actingAs($signedIn);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $other->id,
            'hash' => sha1($other->email),
        ]);

        $this->get($url)
            ->assertRedirect(route('verification.notice'))
            ->assertSessionHas('error', __('auth.verification_failed'));
        $this->assertNull($other->fresh()?->email_verified_at);
        $this->assertNull($signedIn->fresh()?->email_verified_at);
    }

    public function test_a_pending_email_link_for_another_account_is_rejected(): void
    {
        $signedIn = UserModel::factory()->create();
        $other = UserModel::factory()->create(['email' => 'other@example.com', 'pending_email' => 'new@example.com']);
        $this->actingAs($signedIn);

        $url = URL::temporarySignedRoute('verification.pending-email', now()->addHour(), [
            'id' => $other->id,
            'hash' => sha1('new@example.com'),
        ]);

        $this->get($url)
            ->assertRedirect(route('profile.show'))
            ->assertSessionHas('error', __('auth.email_change_verification_failed'));
        $fresh = $other->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('other@example.com', $fresh->email);
        $this->assertSame('new@example.com', $fresh->pending_email);
    }

    public function test_a_verified_account_is_sent_from_the_notice_to_the_page_it_asked_for(): void
    {
        $this->actingAs(UserModel::factory()->create());

        $this->get(route('verification.notice'))->assertRedirect(route('home'));

        $this->withSession(['url.intended' => url('/perfil')])
            ->get(route('verification.notice'))
            ->assertRedirect(url('/perfil'));
    }

    public function test_the_verification_link_falls_back_to_home_without_a_page_asked_for(): void
    {
        $user = UserModel::factory()->unverified()->create();
        $this->actingAs($user);

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);

        $this->get($url)->assertRedirect(route('home'))->assertSessionHas('success');
        $this->assertNotNull($user->fresh()?->email_verified_at);
    }

    public function test_sending_the_verification_email_again_is_throttled(): void
    {
        Notification::fake();
        $this->actingAs(UserModel::factory()->unverified()->create());

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('verification.send'))->assertRedirect();
        }

        $this->post(route('verification.send'))->assertTooManyRequests();
    }
}
