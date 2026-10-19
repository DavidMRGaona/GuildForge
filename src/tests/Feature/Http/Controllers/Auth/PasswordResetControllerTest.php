<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class PasswordResetControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app()->setLocale('es');
    }

    public function test_requesting_a_reset_link_confirms_in_spanish(): void
    {
        Notification::fake();
        $user = UserModel::factory()->create(['email' => 'john@example.com']);

        $response = $this->post('/olvide-contrasena', ['email' => 'john@example.com']);

        $response->assertSessionHas('success', 'Si existe una cuenta con ese email, te hemos enviado un enlace para restablecer la contraseña.');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_requesting_a_reset_link_for_an_unknown_email_gives_the_same_answer(): void
    {
        Notification::fake();

        $response = $this->post('/olvide-contrasena', ['email' => 'nadie@example.com']);

        $response->assertSessionHas('success', 'Si existe una cuenta con ese email, te hemos enviado un enlace para restablecer la contraseña.');
        Notification::assertNothingSent();
    }

    public function test_valid_token_resets_the_password(): void
    {
        $user = UserModel::factory()->create(['email' => 'john@example.com']);
        $token = Password::createToken($user);

        $response = $this->post('/restablecer-contrasena', [
            'token' => $token,
            'email' => 'john@example.com',
            'password' => 'nueva-clave-segura',
            'password_confirmation' => 'nueva-clave-segura',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('success', 'Tu contraseña se ha restablecido.');
        $this->assertTrue(Hash::check('nueva-clave-segura', (string) $user->fresh()?->password));
    }

    public function test_invalid_token_does_not_claim_success(): void
    {
        $user = UserModel::factory()->create(['email' => 'john@example.com']);
        $oldHash = (string) $user->password;

        $response = $this->from('/restablecer-contrasena/caducado')->post('/restablecer-contrasena', [
            'token' => 'caducado',
            'email' => 'john@example.com',
            'password' => 'nueva-clave-segura',
            'password_confirmation' => 'nueva-clave-segura',
        ]);

        $response->assertRedirect('/restablecer-contrasena/caducado');
        $response->assertSessionHasErrors(['email' => 'El enlace para restablecer la contraseña no es válido o ha caducado.']);
        $response->assertSessionMissing('success');
        $this->assertSame($oldHash, (string) $user->fresh()?->password);
    }

    public function test_unknown_email_gets_the_same_error_as_an_invalid_token(): void
    {
        $response = $this->post('/restablecer-contrasena', [
            'token' => 'cualquiera',
            'email' => 'nadie@example.com',
            'password' => 'nueva-clave-segura',
            'password_confirmation' => 'nueva-clave-segura',
        ]);

        $response->assertSessionHasErrors(['email' => 'El enlace para restablecer la contraseña no es válido o ha caducado.']);
    }

    public function test_wrong_login_password_error_is_in_spanish(): void
    {
        UserModel::factory()->create(['email' => 'john@example.com']);

        $response = $this->post('/iniciar-sesion', [
            'email' => 'john@example.com',
            'password' => 'incorrecta',
        ]);

        $response->assertSessionHasErrors(['email' => 'Estas credenciales no coinciden con nuestros registros.']);
    }
}
