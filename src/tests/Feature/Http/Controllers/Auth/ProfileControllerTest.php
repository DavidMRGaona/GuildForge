<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class ProfileControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_updating_the_profile_is_throttled(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create();
        $this->actingAs($user);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('profile.update'), [
                'name' => $user->name,
                'email' => "new{$attempt}@example.com",
            ])->assertRedirect();
        }

        $this->post(route('profile.update'), [
            'name' => $user->name,
            'email' => 'new7@example.com',
        ])->assertTooManyRequests();
    }

    public function test_the_profile_has_its_own_limit_apart_from_resending_the_verification_link(): void
    {
        Notification::fake();
        $user = UserModel::factory()->unverified()->create();
        $this->actingAs($user);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post(route('verification.send'))->assertRedirect();
        }
        $this->post(route('verification.send'))->assertTooManyRequests();

        $this->post(route('profile.update'), [
            'name' => $user->name,
            'email' => 'corrected@example.com',
        ])->assertRedirect()->assertSessionHasNoErrors();
    }
}
