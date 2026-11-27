<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Controllers\Auth;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserProfileModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * Registering asks for the public name (core#855): every new account is shown on the site by a
 * name it chose, and its profile is born with an address that follows it.
 */
final class RegisterPublicNameTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    public function test_registering_without_a_public_name_is_refused(): void
    {
        foreach ([null, '', '   '] as $value) {
            $this->post('/registro', [...$this->account(), 'display_name' => $value])
                ->assertSessionHasErrors('display_name');
        }

        $this->post('/registro', array_diff_key($this->account(), ['display_name' => true]))
            ->assertSessionHasErrors('display_name');

        $this->assertFalse(UserModel::query()->where('email', 'nuevo@example.com')->exists());
    }

    public function test_a_public_name_longer_than_the_column_is_refused(): void
    {
        $this->post('/registro', [...$this->account(), 'display_name' => str_repeat('a', 256)])
            ->assertSessionHasErrors('display_name');
    }

    public function test_the_public_name_is_saved_with_the_new_account(): void
    {
        $this->post('/registro', $this->account())->assertRedirect(route('home'));

        $user = UserModel::query()->where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertSame('Nuevo usuario', $user->name);
        $this->assertSame('Lady Ana', $user->display_name);
    }

    public function test_the_profile_is_born_with_an_address_that_follows_the_public_name(): void
    {
        $this->post('/registro', $this->account())->assertRedirect();

        $user = UserModel::query()->where('email', 'nuevo@example.com')->firstOrFail();
        $this->assertSame('lady-ana', UserProfileModel::query()->findOrFail($user->id)->handle);
    }

    public function test_a_new_account_never_sees_the_screen_to_choose_one(): void
    {
        $this->post('/registro', $this->account());

        $this->get(route('profile.show'))->assertOk();
    }

    /**
     * @return array<string, string>
     */
    private function account(): array
    {
        return [
            'name' => 'Nuevo usuario',
            'display_name' => 'Lady Ana',
            'email' => 'nuevo@example.com',
            'password' => 'secret-password',
            'password_confirmation' => 'secret-password',
        ];
    }
}
