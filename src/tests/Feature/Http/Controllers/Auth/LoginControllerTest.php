<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

final class LoginControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_login_page_is_displayed(): void
    {
        $response = $this->get('/iniciar-sesion');

        $response->assertStatus(200);
    }

    public function test_user_can_login_with_correct_credentials(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'user@example.com',
        ]);

        $response = $this->post('/iniciar-sesion', [
            'email' => 'user@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_wrong_password(): void
    {
        UserModel::factory()->create([
            'email' => 'user@example.com',
        ]);

        $response = $this->post('/iniciar-sesion', [
            'email' => 'user@example.com',
            'password' => 'wrong-password',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_user_can_login_with_different_email_casing(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'user@example.com',
        ]);

        $response = $this->post('/iniciar-sesion', [
            'email' => 'USER@EXAMPLE.COM',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_created_with_uppercase_email_can_login_with_lowercase(): void
    {
        $user = UserModel::factory()->create([
            'email' => 'UPPERCASE@EXAMPLE.COM',
        ]);

        // The email mutator should have normalized it to lowercase in the DB
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'email' => 'uppercase@example.com',
        ]);

        $response = $this->post('/iniciar-sesion', [
            'email' => 'uppercase@example.com',
            'password' => 'password',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_login_limit_counts_an_email_however_it_is_written(): void
    {
        UserModel::factory()->create(['email' => 'persona@example.com']);

        foreach (['persona@example.com', 'Persona@example.com', 'PERSONA@example.com', ' persona@example.com', 'persona@EXAMPLE.com'] as $email) {
            $this->post('/iniciar-sesion', ['email' => $email, 'password' => 'wrong'])->assertSessionHasErrors();
        }

        $this->post('/iniciar-sesion', ['email' => 'Persona@Example.com', 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_user_can_logout(): void
    {
        $user = UserModel::factory()->create();

        $response = $this->actingAs($user)->post('/cerrar-sesion');

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }
}
