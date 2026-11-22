<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles\Services;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Domain\Profiles\Events\ProfileMembershipDisplayChanged;
use App\Infrastructure\Impersonation\SafetyNet\ImpersonationSafetyNet;
use App\Infrastructure\Profiles\ProfileContributionRegistry;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\DatabaseTransactionRecord;
use Illuminate\Support\Str;
use Throwable;
use WeakMap;

/**
 * Dispatches ProfileMembershipDisplayChanged (core#824) once the transaction of the default connection
 * commits, at once outside one, and once per account and outermost transaction (ids in lower case: savepoints
 * inside it share its announcements, and one that rolls back takes its own with it). Nothing
 * while an impersonation lasts or inside its safety net: the change will be rolled back and nothing may
 * leave the request. Every place that can change what ProfileMembershipDisplayInterface answers calls it:
 * the observers, the roles listener and the job of the role setting.
 *
 * Bound scoped, like the activity publisher it follows: a listener's failure (a queue that cannot take
 * the job) is warned once a day, by its class, and never reaches what changed.
 */
final readonly class ProfileMembershipDisplayAnnouncer
{
    private DailyWarnings $warnings;

    /**
     * The accounts already announced by each outermost transaction, written when the announcement goes out,
     * so what a rolled back savepoint dropped never counts. Keyed by Laravel's record of the transaction: the
     * callbacks hold it until they run, and it is dropped with them.
     *
     * @var WeakMap<DatabaseTransactionRecord, array<string, true>>
     */
    private WeakMap $announced;

    public function __construct(
        private DatabaseManager $database,
        private ImpersonationContextInterface $impersonation,
        private ImpersonationSafetyNet $safetyNet,
        private Container $container,
    ) {
        $this->warnings = new DailyWarnings(
            ProfileContributionRegistry::WARNING_PREFIX,
            static fn (): Cache => $container->make(Cache::class),
        );
        $this->announced = new WeakMap;
    }

    public function changed(string $userId): void
    {
        if ($this->impersonation->isActive() || $this->safetyNet->isEngaged() || ! Str::isUuid($userId)) {
            return;
        }

        $userId = strtolower($userId);
        $transaction = $this->outermostTransaction();

        $this->database->connection()->afterCommit(function () use ($userId, $transaction): void {
            if ($transaction instanceof DatabaseTransactionRecord) {
                $announced = $this->announced[$transaction] ?? [];

                // Announced already by another save of the same transaction, nested or not
                if (isset($announced[$userId])) {
                    return;
                }

                $announced[$userId] = true;
                $this->announced[$transaction] = $announced;
            }

            $this->dispatch($userId);
        });
    }

    /**
     * At once, for what already happened and committed (the job of the role setting).
     */
    public function dispatch(string $userId): void
    {
        try {
            // Asked for now: a long-lived worker must not keep a dispatcher that was swapped meanwhile
            $this->container->make(Dispatcher::class)->dispatch(new ProfileMembershipDisplayChanged(strtolower($userId)));
        } catch (Throwable $exception) {
            // Once a day: a queue that is down would otherwise log on every save
            $this->warnings->warn('announcer|'.$exception::class, 'A change of the membership display of a profile could not be announced.', [
                'reason' => 'dispatch',
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * The outermost open transaction of the default connection whose callbacks wait for a commit, or null
     * outside one (a test's own wrapping transaction does not count, as for afterCommit()). Every callback
     * registered inside it, in a savepoint or not, runs when it commits.
     */
    private function outermostTransaction(): ?DatabaseTransactionRecord
    {
        $connection = $this->database->connection();

        if ($connection->transactionLevel() === 0 || ! $this->container->bound('db.transactions')) {
            return null;
        }

        $transactions = $this->container->make('db.transactions');
        $name = $connection->getName();

        return $transactions->callbackApplicableTransactions()
            ->first(static fn (DatabaseTransactionRecord $record): bool => $record->connection === $name);
    }
}
