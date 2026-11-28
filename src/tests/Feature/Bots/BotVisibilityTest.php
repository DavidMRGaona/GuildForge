<?php

declare(strict_types=1);

namespace Tests\Feature\Bots;

use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

final class BotVisibilityTest extends TestCase
{
    use RefreshDatabase;

    private UserModel $person;

    private UserModel $bot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->person = UserModel::factory()->create();
        $this->bot = UserModel::factory()->create();
        $this->bot->forceFill(['bot_key' => 'forum.publisher'])->saveQuietly();
    }

    public function test_queries_leave_bots_out_unless_asked(): void
    {
        $this->assertSame([$this->person->id], UserModel::query()->pluck('id')->all());
        $this->assertSame(1, UserModel::withTrashed()->count());
        $this->assertSame(2, UserModel::query()->withBots()->count());
        $this->assertNull(UserModel::find($this->bot->id));
        $this->assertTrue(UserModel::query()->withBots()->findOrFail($this->bot->id)->isBot());
        $this->assertFalse($this->person->isBot());
    }

    public function test_bot_key_is_not_mass_assignable(): void
    {
        $user = new UserModel(['name' => 'Someone', 'bot_key' => 'forum.publisher']);

        $this->assertNull($user->bot_key);
    }

    public function test_a_bot_cannot_sign_in_even_with_the_right_password(): void
    {
        $this->assertFalse(Auth::guard('web')->validate(['email' => $this->bot->email, 'password' => 'password']));
        $this->assertTrue(Auth::guard('web')->validate(['email' => $this->person->email, 'password' => 'password']));
    }

    public function test_a_bot_cannot_reset_its_password(): void
    {
        $this->assertSame(Password::INVALID_USER, Password::sendResetLink(['email' => $this->bot->email]));
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_a_bot_session_ends(): void
    {
        $provider = Auth::guard('web')->getProvider();

        $this->assertNull($provider->retrieveById($this->bot->id));
        $this->assertNotNull($provider->retrieveById($this->person->id));
    }
}
