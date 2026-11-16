<?php

declare(strict_types=1);

namespace Tests\Integration\Infrastructure\TwoFactor;

use App\Application\TwoFactor\Services\TwoFactorServiceInterface;
use App\Infrastructure\Persistence\Eloquent\Models\UserModel;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Two requests racing with the same code, on two real connections (PostgreSQL only: SQLite has no
 * row locks). The first holds the row lock until it commits; the second waits on it and, once the
 * first committed, finds the code already used.
 *
 * The rows are committed (no wrapping transaction, so the second connection sees them) and deleted
 * at the end.
 */
final class TwoFactorRaceTest extends TestCase
{
    use RefreshDatabase;

    private const int NOW = 1_800_000_015;

    private const string SECOND = 'two_factor_race';

    /** @var list<string> */
    protected array $connectionsToTransact = [];

    private ?UserModel $user = null;

    /** @var list<string> */
    private array $handlesBefore = [];

    protected function setUp(): void
    {
        // Before the application boots: with no wrapping transaction, booting it on SQLite would
        // leave the in-memory database marked as migrated for the next test classes
        $connection = getenv('DB_CONNECTION') ?: ($_ENV['DB_CONNECTION'] ?? '');
        if ($connection !== 'pgsql') {
            $this->markTestSkipped('Row locks need PostgreSQL');
        }

        parent::setUp();

        Carbon::setTestNow(Carbon::createFromTimestamp(self::NOW));
        Notification::fake();
        $this->handlesBefore = array_values(DB::table('user_profile_handles')->pluck('handle_hash')->map(static fn (mixed $hash): string => (string) $hash)->all());
        $default = (string) config('database.default');
        config(['database.connections.'.self::SECOND => config('database.connections.'.$default)]);
    }

    protected function tearDown(): void
    {
        if ($this->app === null) {
            parent::tearDown();

            return;
        }

        if ($this->user instanceof UserModel) {
            DB::setDefaultConnection((string) config('database.default'));
            $this->user->forceDelete();
            // Address history outlives accounts on purpose; this test's own rows go, so later tests start clean
            DB::table('user_profile_handles')->whereNotIn('handle_hash', $this->handlesBefore)->delete();
        }

        DB::purge(self::SECOND);
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_a_recovery_code_raced_by_two_requests_passes_once(): void
    {
        [, $codes] = $this->enable();

        $this->race(
            fn (): bool => $this->service()->useRecoveryCode((string) $this->user?->id, $codes[0]),
            [true, false],
        );
        $this->assertSame(7, $this->service()->status((string) $this->user?->id)->recoveryCodesRemaining);
    }

    public function test_a_code_raced_by_two_requests_passes_once(): void
    {
        [$secret] = $this->enable();
        $code = (new Google2FA)->oathTotp($secret, intdiv(self::NOW, 30) + 1);

        $this->race(fn (): bool => $this->service()->verify((string) $this->user?->id, $code), [true, false]);
    }

    /**
     * The first call runs inside an open transaction on the default connection, so it keeps the
     * row locked; the second, on another connection, must wait for that lock (it gives up after a
     * short timeout, which proves it waited), and once the first committed it is refused.
     *
     * @param  Closure(): bool  $attempt
     * @param  array{bool, bool}  $expected
     */
    private function race(Closure $attempt, array $expected): void
    {
        $default = (string) config('database.default');
        DB::connection($default)->beginTransaction();
        $first = $attempt();

        DB::setDefaultConnection(self::SECOND);
        DB::connection(self::SECOND)->statement("SET lock_timeout = '300ms'");

        try {
            $attempt();
            $this->fail('The second attempt read the row without waiting for the lock');
        } catch (QueryException $exception) {
            $this->assertSame('55P03', $exception->getCode(), 'Expected a lock timeout');
        } finally {
            DB::setDefaultConnection($default);
        }

        DB::connection($default)->commit();

        DB::setDefaultConnection(self::SECOND);
        try {
            $second = $attempt();
        } finally {
            DB::setDefaultConnection($default);
        }

        $this->assertSame($expected, [$first, $second]);
    }

    /**
     * @return array{string, list<string>}
     */
    private function enable(): array
    {
        $this->user = UserModel::factory()->create();
        $setup = $this->service()->beginSetup((string) $this->user->id);
        $codes = $this->service()->confirmSetup((string) $this->user->id, (new Google2FA)->oathTotp($setup->secret, intdiv(self::NOW, 30)));

        return [$setup->secret, $codes];
    }

    private function service(): TwoFactorServiceInterface
    {
        return $this->app->make(TwoFactorServiceInterface::class);
    }
}
