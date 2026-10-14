<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
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
