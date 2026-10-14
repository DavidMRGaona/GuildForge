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
}
