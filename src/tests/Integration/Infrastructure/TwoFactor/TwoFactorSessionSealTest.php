<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\TwoFactor;

use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Application\TwoFactor\Services\TwoFactorSessionSealInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use App\Infrastructure\TwoFactor\TwoFactorSessionSeal;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Session\ArraySessionHandler;
use Illuminate\Session\Store;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Only sessions holding the account's seal stay signed in once two-factor authentication is on.
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

    public function test_without_two_factor_any_session_passes(): void
    {
        $user = UserModel::factory()->create();
        $session = $this->newSession();

        $this->assertTrue($this->seal()->passes($session, (string) $user->id));

        $this->service()->beginSetup((string) $user->id);
        $this->assertTrue($this->seal()->passes($session, (string) $user->id));

        $this->seal()->stamp($session, (string) $user->id);
        $this->assertFalse($session->has(TwoFactorSessionSeal::SESSION_KEY));
    }

    public function test_with_two_factor_only_a_stamped_session_passes(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $before = $this->newSession();
        $challenged = $this->newSession();

        $this->assertFalse($this->seal()->passes($before, (string) $user->id));

        $this->seal()->stamp($challenged, (string) $user->id);
        $this->assertTrue($this->seal()->passes($challenged, (string) $user->id));
        $this->assertTrue($this->seal()->passes($challenged, strtoupper((string) $user->id)));
    }

    public function test_a_seal_from_another_account_or_an_earlier_setup_does_not_pass(): void
    {
        $user = UserModel::factory()->create();
        $other = UserModel::factory()->create();
        $this->enable($user);
        $this->enable($other);
        $session = $this->newSession();
        $this->seal()->stamp($session, (string) $other->id);

        $this->assertFalse($this->seal()->passes($session, (string) $user->id));

        // Turned off and on again: a new seal
        $this->seal()->stamp($session, (string) $user->id);
        $this->service()->disable((string) $user->id);
        $this->assertTrue($this->seal()->passes($session, (string) $user->id));
        $this->enable($user, offset: 1);
        $this->assertFalse($this->seal()->passes($session, (string) $user->id));
    }

    public function test_a_tampered_session_value_does_not_pass(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $session = $this->newSession();

        $session->put(TwoFactorSessionSeal::SESSION_KEY, ['not', 'a', 'string']);
        $this->assertFalse($this->seal()->passes($session, (string) $user->id));

        $session->put(TwoFactorSessionSeal::SESSION_KEY, '');
        $this->assertFalse($this->seal()->passes($session, (string) $user->id));
    }

    public function test_it_fails_closed_when_the_seal_cannot_be_read(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        UserTwoFactorModel::resolveConnection()->getSchemaBuilder()->drop('user_two_factor');

        $this->assertFalse($this->seal()->passes($this->newSession(), (string) $user->id));
    }

    public function test_an_id_that_is_no_account_passes_nothing_to_compare(): void
    {
        $this->assertTrue($this->seal()->passes($this->newSession(), 'not-a-uuid'));
    }

    private function enable(UserModel $user, int $offset = 0): void
    {
        $setup = $this->service()->beginSetup((string) $user->id);
        $this->service()->confirmSetup((string) $user->id, (new Google2FA)->oathTotp($setup->secret, intdiv(self::NOW, 30) + $offset));
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
