<?php

declare(strict_types=1);

namespace Tests\Feature\Bots;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Application\Bots\Services\BotRegistryInterface;
use App\Application\Bots\Services\BotsInterface;
use App\Application\Services\SiteBrandingServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorStatusReaderInterface;
use App\Domain\Bots\Exceptions\UnknownBotException;
use App\Domain\Events\UserRestored;
use App\Infrastructure\Bots\BotRegistry;
use App\Infrastructure\Persistence\Eloquent\Models\RoleModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\Bots\FakeBot;
use Tests\Support\Bots\RecordingActivityPublisher;
use Tests\TestCase;

/**
 * Turning an account that never belonged to a person into a bot (core#863). A wrong rule would destroy
 * a real member's login, so the refusals are the heart of these tests.
 */
final class BotAdoptionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const string KEY = 'forum.publisher';

    private RecordingActivityPublisher $publisher;

    protected function setUp(): void
    {
        parent::setUp();

        $branding = Mockery::mock(SiteBrandingServiceInterface::class);
        $branding->shouldReceive('siteName')->andReturn('Asociación de ejemplo');
        $this->app->instance(SiteBrandingServiceInterface::class, $branding);

        $registry = new BotRegistry($this->app);
        $registry->register(FakeBot::class, 'forum');
        $this->app->instance(BotRegistryInterface::class, $registry);

        $this->publisher = new RecordingActivityPublisher;
        $this->app->instance(ActivityPublisherInterface::class, $this->publisher);
    }

    private function bots(): BotsInterface
    {
        return $this->app->make(BotsInterface::class);
    }

    /** @param array<string, mixed> $overrides */
    private function adoptable(array $overrides = []): UserModel
    {
        $user = UserModel::factory()->create(array_merge([
            'email' => 'forum-system-'.Str::uuid().'@system.invalid',
            'email_verified_at' => null,
        ], $overrides));
        $this->publisher->calls = [];

        return $user;
    }

    private function role(string $name): RoleModel
    {
        return RoleModel::query()->firstOrCreate(['name' => $name], ['display_name' => $name]);
    }

    private function id(UserModel $user): string
    {
        return strtolower((string) $user->getKey());
    }

    private function fresh(string $id): UserModel
    {
        return UserModel::query()->withBots()->withTrashed()->findOrFail($id);
    }

    private function twoFactor(bool|RuntimeException $answer): void
    {
        $reader = Mockery::mock(TwoFactorStatusReaderInterface::class);
        if ($answer instanceof RuntimeException) {
            $reader->shouldReceive('isEnabled')->andThrow($answer);
        } else {
            $reader->shouldReceive('isEnabled')->andReturn($answer);
        }
        $this->app->instance(TwoFactorStatusReaderInterface::class, $reader);
    }

    public function test_it_adopts_an_account_that_could_not_belong_to_a_person(): void
    {
        $user = $this->adoptable();
        $this->role('member');
        $user->roles()->attach($this->role('member')->id);

        $this->assertTrue($this->bots()->adopt(self::KEY, $this->id($user)));

        $this->assertSame(self::KEY, $this->fresh($this->id($user))->bot_key);
    }

    public function test_it_adopts_an_account_with_no_role_and_a_deactivated_one(): void
    {
        $user = $this->adoptable();
        $user->delete();

        $this->assertTrue($this->bots()->adopt(self::KEY, $this->id($user)));

        $adopted = $this->fresh($this->id($user));
        $this->assertSame(self::KEY, $adopted->bot_key);
        $this->assertNull($adopted->deleted_at);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function refusedAccounts(): array
    {
        return [
            'real email' => [['email' => 'ana@example.com']],
            'invalid-looking but not at an invalid domain' => [['email' => 'x@system.invalid.example.com']],
            'verified email' => [['email_verified_at' => '2026-01-01 00:00:00']],
            'anonymized' => [['anonymized_at' => '2026-01-01 00:00:00']],
        ];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    #[DataProvider('refusedAccounts')]
    public function test_it_refuses_an_account_that_may_be_a_person(array $overrides): void
    {
        $user = $this->adoptable($overrides);
        $before = $user->getAttributes();

        $this->assertFalse($this->bots()->adopt(self::KEY, $this->id($user)));

        $after = $this->fresh($this->id($user));
        $this->assertNull($after->bot_key);
        $this->assertSame($before['password'], $after->password);
        $this->assertSame([], $this->publisher->calls);
    }

    public function test_it_refuses_an_account_with_two_factor_on(): void
    {
        $user = $this->adoptable();
        $this->twoFactor(true);

        $this->assertFalse($this->bots()->adopt(self::KEY, $this->id($user)));
        $this->assertNull($this->fresh($this->id($user))->bot_key);
    }

    public function test_it_refuses_when_two_factor_cannot_be_read(): void
    {
        $user = $this->adoptable();
        $this->twoFactor(new RuntimeException('unreadable'));

        $this->assertFalse($this->bots()->adopt(self::KEY, $this->id($user)));
        $this->assertNull($this->fresh($this->id($user))->bot_key);
    }

    public function test_it_refuses_an_account_with_any_role_besides_member(): void
    {
        $user = $this->adoptable();
        $user->roles()->attach([$this->role('member')->id, $this->role('editor')->id]);

        $this->assertFalse($this->bots()->adopt(self::KEY, $this->id($user)));

        $this->assertNull($this->fresh($this->id($user))->bot_key);
        $this->assertSame(2, DB::table('user_role')->where('user_id', $user->id)->count());
        $this->assertSame(0, DB::table('role_audit_log')->where('user_id', $user->id)->count());
    }

    public function test_it_refuses_the_account_of_another_bot(): void
    {
        $user = $this->adoptable();
        $user->forceFill(['bot_key' => 'other.thing'])->save();

        $this->assertFalse($this->bots()->adopt(self::KEY, $this->id($user)));
        $this->assertSame('other.thing', $this->fresh($this->id($user))->bot_key);
    }

    public function test_it_refuses_an_unknown_account(): void
    {
        $this->assertFalse($this->bots()->adopt(self::KEY, (string) Str::uuid()));
        $this->assertFalse($this->bots()->adopt(self::KEY, 'not-an-id'));
    }

    public function test_an_unregistered_key_throws(): void
    {
        $user = $this->adoptable();

        $this->expectException(UnknownBotException::class);
        $this->bots()->adopt('nobody.here', $this->id($user));
    }

    public function test_the_effects_of_an_adoption(): void
    {
        $user = $this->adoptable(['remember_token' => 'abc']);
        $id = $this->id($user);
        $oldPassword = $user->password;
        $user->roles()->attach($this->role('member')->id);
        $this->assertSame(1, DB::table('user_profiles')->where('user_id', $id)->count());

        DB::table('site_notifications')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $id, 'category' => 'x', 'title' => 't', 'url' => '/a',
            'notified_at' => now(), 'sort_key' => 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('user_site_notification_seen')->insert(['user_id' => $id, 'seen_key' => 5]);
        DB::table('web_push_subscriptions')->insert([
            'id' => (string) Str::uuid(), 'user_id' => $id, 'endpoint' => 'https://fcm.googleapis.com/x',
            'endpoint_hash' => str_repeat('a', 64), 'p256dh' => 'p', 'auth' => 'a', 'browser' => 'chrome',
            'platform' => 'linux', 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 't', 'created_at' => now()]);
        $user->delete();
        $this->publisher->calls = [];
        Event::fake([UserRestored::class]);

        $this->assertTrue($this->bots()->adopt(self::KEY, $id));

        $adopted = $this->fresh($id);
        $this->assertSame(self::KEY, $adopted->bot_key);
        $this->assertNotSame($oldPassword, $adopted->password);
        $this->assertFalse(Hash::check('password', $adopted->password));
        $this->assertNull(DB::table('users')->where('id', $id)->value('remember_token'));
        $this->assertNull($adopted->deleted_at);
        Event::assertNotDispatched(UserRestored::class);

        $this->assertSame(0, DB::table('user_role')->where('user_id', $id)->count());
        $audit = DB::table('role_audit_log')->where('user_id', $id)->first();
        $this->assertNotNull($audit);
        $this->assertSame(1, DB::table('role_audit_log')->where('user_id', $id)->count());
        $this->assertSame('removed', $audit->event);
        $this->assertSame('system', $audit->source);

        $this->assertSame(0, DB::table('user_profiles')->where('user_id', $id)->count());
        $this->assertSame(1, DB::table('user_profile_handles')->count());
        $this->assertContains(['core.account', $id], $this->publisher->calls);
        $this->assertContains(['core.profile-completed', $id], $this->publisher->calls);
        $this->assertSame(0, DB::table('site_notifications')->where('user_id', $id)->count());
        $this->assertSame(0, DB::table('user_site_notification_seen')->where('user_id', $id)->count());
        $this->assertSame(0, DB::table('web_push_subscriptions')->where('user_id', $id)->count());
        $this->assertSame(0, DB::table('password_reset_tokens')->where('email', $user->email)->count());
    }

    public function test_adopting_twice_changes_nothing_the_second_time(): void
    {
        $user = $this->adoptable();
        $id = $this->id($user);
        $user->roles()->attach($this->role('member')->id);

        $this->assertTrue($this->bots()->adopt(self::KEY, $id));
        $password = $this->fresh($id)->password;
        $audit = DB::table('role_audit_log')->count();
        $this->publisher->calls = [];

        $this->assertTrue($this->bots()->adopt(self::KEY, $id));

        $this->assertSame($password, $this->fresh($id)->password);
        $this->assertSame($audit, DB::table('role_audit_log')->count());
        $this->assertSame([], $this->publisher->calls);
    }

    public function test_the_adopted_account_is_what_id_returns(): void
    {
        $user = $this->adoptable();
        $this->bots()->adopt(self::KEY, $this->id($user));
        $count = UserModel::query()->withBots()->withTrashed()->count();

        $this->assertSame($this->id($user), $this->bots()->id(self::KEY));
        $this->assertSame($count, UserModel::query()->withBots()->withTrashed()->count());
    }

    public function test_an_unreadable_two_factor_state_refuses_and_warns_once_a_day(): void
    {
        Cache::flush();
        $first = $this->adoptable();
        Log::shouldReceive('warning')->once()->withArgs(
            fn (string $message, array $context): bool => $context === ['user_id' => $this->id($first), 'exception' => RuntimeException::class],
        );
        $this->twoFactor(new RuntimeException('secret detail ana@example.com'));

        $this->assertFalse($this->bots()->adopt(self::KEY, $this->id($first)));
        $this->assertFalse($this->bots()->adopt(self::KEY, $this->id($first)));
    }

    public function test_a_key_already_held_by_another_account_refuses_without_effects(): void
    {
        $botId = $this->bots()->id(self::KEY);
        $other = $this->adoptable();
        $other->roles()->attach($this->role('member')->id);
        $password = $other->password;
        $this->publisher->calls = [];

        $this->assertFalse($this->bots()->adopt(self::KEY, $this->id($other)));

        $after = $this->fresh($this->id($other));
        $this->assertNull($after->bot_key);
        $this->assertSame($password, $after->password);
        $this->assertSame(1, DB::table('user_role')->where('user_id', $other->id)->count());
        $this->assertSame(0, DB::table('role_audit_log')->count());
        $this->assertSame([], $this->publisher->calls);
        $this->assertSame($botId, $this->bots()->id(self::KEY));
    }
}
