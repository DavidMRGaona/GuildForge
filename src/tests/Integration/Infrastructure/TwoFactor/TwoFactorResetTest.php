<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\TwoFactor;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\TwoFactor\Services\TwoFactorResetInterface;
use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Domain\TwoFactor\Events\TwoFactorReset;
use App\Domain\TwoFactor\Exceptions\TwoFactorException;
use App\Domain\TwoFactor\TwoFactorFailure;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use App\Infrastructure\Persistence\Eloquent\Models\UserTwoFactorModel;
use Closure;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Resetting somebody's two-factor authentication from the panel or the console (core#771): the
 * secret and the codes are gone, every session and «remember me» cookie of the account stops
 * working, and the owner is told (the event goes out after the commit).
 */
final class TwoFactorResetTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** A moment in the middle of a 30-second step */
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

    public function test_it_deletes_the_secret_and_the_codes_and_announces_it(): void
    {
        $user = UserModel::factory()->create();
        $actor = UserModel::factory()->admin()->create();
        $this->enable($user);
        Event::fake([TwoFactorReset::class]);

        $reset = $this->reset()->reset((string) $user->id, (string) $actor->id);

        $this->assertTrue($reset);
        $this->assertNull(UserTwoFactorModel::query()->find($user->id));
        $this->assertFalse($this->service()->isEnabled((string) $user->id));
        Event::assertDispatched(TwoFactorReset::class, static fn (TwoFactorReset $event): bool => $event->userId === (string) $user->id
            && $event->actorId === (string) $actor->id);
    }

    public function test_it_signs_out_every_session_and_invalidates_remember_me(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $user->refresh();
        $sealBefore = $user->session_seal;
        $tokenBefore = $user->remember_token;
        config(['session.driver' => 'database']);
        $this->storeSession('session-a', $user);
        $this->storeSession('session-b', $user);
        $other = UserModel::factory()->create();
        $this->storeSession('session-other', $other);

        $this->reset()->reset((string) $user->id, null);

        $user->refresh();
        $this->assertNotNull($user->session_seal);
        $this->assertNotSame($sealBefore, $user->session_seal);
        $this->assertNotSame($tokenBefore, $user->remember_token);
        // With the database driver the rows go too (the new seal already signs the sessions out whatever the driver); none is kept
        $this->assertSame(['session-other'], DB::table('sessions')->pluck('id')->all());
    }

    public function test_the_console_has_no_actor(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        Event::fake([TwoFactorReset::class]);

        $this->assertTrue($this->reset()->reset((string) $user->id, null));

        Event::assertDispatched(TwoFactorReset::class, static fn (TwoFactorReset $event): bool => $event->actorId === null);
    }

    public function test_nothing_happens_for_an_account_without_two_factor(): void
    {
        $user = UserModel::factory()->create();
        $before = $user->fresh();
        Event::fake([TwoFactorReset::class]);

        $this->assertFalse($this->reset()->reset((string) $user->id, null));

        Event::assertNotDispatched(TwoFactorReset::class);
        $this->assertSame($before?->remember_token, $user->fresh()?->remember_token);
        $this->assertSame($before?->session_seal, $user->fresh()?->session_seal);
    }

    public function test_a_setup_still_pending_is_dropped_without_an_announcement(): void
    {
        $user = UserModel::factory()->create();
        $this->service()->beginSetup((string) $user->id);
        Event::fake([TwoFactorReset::class]);

        $this->assertFalse($this->reset()->reset((string) $user->id, null));

        $this->assertNull(UserTwoFactorModel::query()->find($user->id));
        Event::assertNotDispatched(TwoFactorReset::class);
    }

    public function test_an_unknown_account_is_refused(): void
    {
        $this->assertRefused(TwoFactorFailure::UnknownAccount, fn () => $this->reset()->reset((string) Str::uuid(), null));
        $this->assertRefused(TwoFactorFailure::UnknownAccount, fn () => $this->reset()->reset('not-an-id', null));
    }

    public function test_it_is_refused_while_impersonating(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        $context = $this->createStub(ImpersonationContextInterface::class);
        $context->method('isActive')->willReturn(true);
        $this->app->instance(ImpersonationContextInterface::class, $context);

        $this->assertRefused(TwoFactorFailure::Impersonating, fn () => $this->reset()->reset((string) $user->id, null));

        $this->assertTrue(UserTwoFactorModel::query()->whereKey($user->id)->exists());
    }

    public function test_nothing_is_announced_when_the_change_rolls_back(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);
        Event::fake([TwoFactorReset::class]);

        try {
            DB::transaction(function () use ($user): void {
                $this->reset()->reset((string) $user->id, null);

                throw new \RuntimeException('rolled back');
            });
        } catch (\RuntimeException) {
        }

        $this->assertTrue($this->service()->isEnabled((string) $user->id));
        Event::assertNotDispatched(TwoFactorReset::class);
    }

    public function test_it_locks_the_account_before_its_two_factor_row(): void
    {
        $user = UserModel::factory()->create();
        $this->enable($user);

        $order = $this->lockedTablesDuring(fn () => $this->reset()->reset((string) $user->id, null));

        $this->assertSame(['users', 'user_two_factor'], array_slice($order, 0, 2));
    }

    private function reset(): TwoFactorResetInterface
    {
        return $this->app->make(TwoFactorResetInterface::class);
    }

    private function service(): TwoFactorServiceInterface
    {
        return $this->app->make(TwoFactorServiceInterface::class);
    }

    private function enable(UserModel $user): void
    {
        $setup = $this->service()->beginSetup((string) $user->id);
        $this->service()->confirmSetup((string) $user->id, (new Google2FA)->oathTotp($setup->secret, intdiv(self::NOW, 30)));
    }

    private function storeSession(string $id, UserModel $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => self::NOW,
        ]);
    }

    private function assertRefused(TwoFactorFailure $reason, Closure $call): void
    {
        try {
            $call();
            $this->fail('Expected the reset to be refused: '.$reason->value);
        } catch (TwoFactorException $exception) {
            $this->assertSame($reason, $exception->reason);
        }
    }

    /**
     * The tables read, in order, by the selects that lock (FOR UPDATE on PostgreSQL; on SQLite, which
     * has no row locks, every select inside the change's transaction).
     *
     * @return list<string>
     */
    private function lockedTablesDuring(Closure $change): array
    {
        $tables = [];
        $base = DB::transactionLevel();
        DB::listen(static function (QueryExecuted $query) use (&$tables, $base): void {
            $sql = strtolower($query->sql);
            $locks = DB::getDriverName() === 'sqlite' ? DB::transactionLevel() > $base : str_contains($sql, 'for update');

            if ($locks && str_starts_with($sql, 'select') && preg_match('/from "([a-z_]+)"/', $sql, $matches) === 1) {
                $tables[] = $matches[1];
            }
        });

        $change();
        DB::flushQueryLog();
        $this->app['events']->forget(QueryExecuted::class);

        return array_values(array_unique($tables));
    }
}
