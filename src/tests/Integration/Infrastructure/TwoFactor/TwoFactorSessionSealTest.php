<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\TwoFactor;

use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use RuntimeException;
use Tests\TestCase;

/**
 * Only sessions holding the account's current seal stay signed in once it has one (core#768).
 */
final class TwoFactorSessionSealTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const int NOW = 1_800_000_015;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_an_account_that_never_had_a_seal_keeps_every_session(): void
    {
        $user = UserModel::factory()->create();
        $session = $this->newSession();

        $this->assertTrue($this->seal()->passes($session, $user));

        $this->service()->beginSetup((string) $user->id);
        $this->assertTrue($this->seal()->passes($session, $this->fresh($user)));

        $this->seal()->stamp($session, (string) $user->id);
        $this->assertFalse($session->has(TwoFactorSessionSeal::SESSION_KEY));
    }

    public function test_turning_it_on_signs_out_older_sessions_and_keeps_the_current_one(): void
    {
        $user = UserModel::factory()->create();
        $old = $this->newSession();
        $current = $this->newSession();

        $this->enable($user, $current);

        $this->assertFalse($this->seal()->passes($old, $this->fresh($user)));
        $this->assertTrue($this->seal()->passes($current, $this->fresh($user)));
    }

    public function test_turning_it_off_signs_out_older_sessions_too(): void
    {
        $user = UserModel::factory()->create();
        $current = $this->newSession();
        $other = $this->newSession();
        $this->enable($user, $current);
        $this->seal()->stamp($other, (string) $user->id);
        $this->assertTrue($this->seal()->passes($other, $this->fresh($user)));

        $this->service()->disable((string) $user->id, $current);

        // The seal survives turning it off: sessions opened before are out, the one that did it stays
        $this->assertNotNull($this->fresh($user)->session_seal);
        $this->assertFalse($this->seal()->passes($other, $this->fresh($user)));
        $this->assertTrue($this->seal()->passes($current, $this->fresh($user)));
    }

    public function test_stamping_copies_the_current_seal(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $session = $this->newSession();

        $this->assertFalse($this->seal()->passes($session, $this->fresh($user)));

        $this->seal()->stamp($session, strtoupper((string) $user->id));
        $this->assertTrue($this->seal()->passes($session, $this->fresh($user)));
    }

    public function test_renewing_rotates_the_seal_and_the_remember_token_only_after_the_commit(): void
    {
        $user = UserModel::factory()->create(['remember_token' => 'old-remember-token']);
        $session = $this->newSession();

        try {
            DB::transaction(function () use ($user, $session): void {
                $this->seal()->renew((string) $user->id, $session);

                throw new RuntimeException('Rolled back');
            });
        } catch (RuntimeException) {
        }

        $this->assertNull($this->fresh($user)->session_seal);
        $this->assertSame('old-remember-token', $this->fresh($user)->getRememberToken());
        $this->assertFalse($session->has(TwoFactorSessionSeal::SESSION_KEY));

        $this->seal()->renew((string) $user->id, $session);

        $seal = (string) $this->fresh($user)->session_seal;
        $this->assertSame(TwoFactorSessionSeal::LENGTH, strlen($seal));
        $this->assertSame($seal, $session->get(TwoFactorSessionSeal::SESSION_KEY));
        $this->assertNotSame('old-remember-token', $this->fresh($user)->getRememberToken());
    }

    public function test_a_seal_from_another_account_does_not_pass(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->enable($user);
        $this->enable($other);
        $session = $this->newSession();
        $this->seal()->stamp($session, (string) $other->id);

        $this->assertFalse($this->seal()->passes($session, $this->fresh($user)));
    }

    public function test_a_tampered_session_value_does_not_pass(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $session = $this->newSession();

        $session->put(TwoFactorSessionSeal::SESSION_KEY, ['not', 'a', 'string']);
        $this->assertFalse($this->seal()->passes($session, $this->fresh($user)));

        $session->put(TwoFactorSessionSeal::SESSION_KEY, '');
        $this->assertFalse($this->seal()->passes($session, $this->fresh($user)));
    }

    public function test_any_owner_is_checked_against_the_stored_seal(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $session = $this->newSession();
        $owner = $this->createStub(Authenticatable::class);
        $owner->method('getAuthIdentifier')->willReturn((string) $user->id);

        $this->assertFalse($this->seal()->passes($session, $owner));

        $this->seal()->stamp($session, (string) $user->id);
        $this->assertTrue($this->seal()->passes($session, $owner));
    }

    public function test_it_fails_closed_when_the_seal_cannot_be_read(): void
    {
        $owner = $this->createStub(Authenticatable::class);
        $owner->method('getAuthIdentifier')->willThrowException(new RuntimeException('Unreadable'));

        $this->assertFalse($this->seal()->passes($this->newSession(), $owner));
    }

    private function enable(UserModel $user, ?Store $session = null): void
    {
        $setup = $this->service()->beginSetup((string) $user->id);
        $this->service()->confirmSetup((string) $user->id, (new Google2FA)->oathTotp($setup->secret, intdiv(self::NOW, 30)), $session);
    }

    private function fresh(UserModel $user): UserModel
    {
        $fresh = $user->fresh();
        $this->assertNotNull($fresh);

        return $fresh;
    }

    private function newSession(): Store
    {
        return new Store('test', new ArraySessionHandler(10), Str::random(40));
    }

    private function seal(): TwoFactorSessionSealInterface
    {
        return $this->app->make(TwoFactorSessionSealInterface::class);
    }

    private function service(): TwoFactorServiceInterface
    {
        return $this->app->make(TwoFactorServiceInterface::class);
    }
}
