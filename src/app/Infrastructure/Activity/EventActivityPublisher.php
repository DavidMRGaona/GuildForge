<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity;

use App\Application\Activity\Services\ActivityPublisherInterface;
use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Domain\Activity\ActivityIdentifierRule;
use App\Domain\Activity\Events\ActivityRecorded;
use App\Domain\Activity\ValueObjects\ActivitySourceType;
use App\Infrastructure\Impersonation\SafetyNet\ImpersonationSafetyNet;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\DatabaseTransactionRecord;
use Throwable;
use WeakMap;

/**
 * Dispatches ActivityRecorded once the transaction of the default connection commits (core#774), at
 * once outside one, and once per subject within one outermost transaction, savepoints included (UUID ids in lower case).
 * Nothing while an impersonation lasts or inside its safety net: the change will be rolled back, and nothing may leave the request. A listener's failure (a queue that cannot take the
 * job) is warned once a day, by its class, and never reaches the module that published.
 */
final readonly class EventActivityPublisher implements ActivityPublisherInterface
{
    private DailyWarnings $warnings;

    /**
     * What each open transaction already announces, so a subject saved several times in one is
     * announced once. Keyed by Laravel's record of the transaction: when it rolls back the record
     * is dropped, and its entry with it.
     *
     * @var WeakMap<DatabaseTransactionRecord, array<string, AnnouncementMark>>
     */
    private WeakMap $pending;

    public function __construct(
        private Dispatcher $events,
        private DatabaseManager $database,
        private ImpersonationContextInterface $impersonation,
        private ImpersonationSafetyNet $safetyNet,
        private Container $container,
    ) {
        $this->warnings = new DailyWarnings(
            ActivitySourceRegistry::WARNING_PREFIX,
            static fn (): Cache => $container->make(Cache::class),
        );
        $this->pending = new WeakMap;
    }

    public function recorded(string $sourceType, string $subjectId): void
    {
        if ($this->impersonation->isActive() || $this->safetyNet->isEngaged()) {
            return;
        }

        $normalizedId = ActivityIdentifierRule::stableId($subjectId);

        if (! ActivitySourceType::tryFrom($sourceType) instanceof ActivitySourceType || $normalizedId === null) {
            // The type only when it is one: the id may be anything a module passed
            $this->warnings->warn('publisher|'.$sourceType, 'An activity was published with an invalid type or subject id; it was dropped.', [
                'type' => ActivitySourceType::tryFrom($sourceType)?->value,
                'reason' => 'invalid_publication',
            ]);

            return;
        }

        $event = new ActivityRecorded($sourceType, $normalizedId);
        // Shared by every callback of the same subject in the same outermost transaction: the first one
        // that runs once it commits announces it, the others find it announced. A savepoint that rolls
        // back takes its callback with it, and the subject recorded again afterwards still gets one.
        $announced = $this->announcedInCurrentTransaction($sourceType.'|'.$normalizedId);

        $this->database->connection()->afterCommit(function () use ($event, $announced): void {
            if ($announced->done) {
                return;
            }

            $announced->done = true;

            try {
                $this->events->dispatch($event);
            } catch (Throwable $exception) {
                // Once a day per type: a queue that is down would otherwise log on every save
                $this->warnings->warn('publisher-dispatch|'.$event->sourceType.'|'.$exception::class, 'An activity could not be announced; a recalculation picks it up.', [
                    'type' => $event->sourceType,
                    'reason' => 'dispatch',
                    'exception' => $exception::class,
                ]);
            }
        });
    }

    /**
     * Whether a subject was already announced, as a mark shared by everything published for it in the
     * outermost open transaction (a fresh one outside any).
     */
    private function announcedInCurrentTransaction(string $key): AnnouncementMark
    {
        $transaction = $this->outermostTransaction();

        if (! $transaction instanceof DatabaseTransactionRecord) {
            return new AnnouncementMark;
        }

        $announced = $this->pending[$transaction] ?? [];
        $announced[$key] ??= new AnnouncementMark;
        $this->pending[$transaction] = $announced;

        return $announced[$key];
    }

    /**
     * The outermost open transaction of the default connection whose callbacks wait for a commit, or
     * null outside one (a test's own wrapping transaction does not count, as for afterCommit()). A
     * savepoint is a transaction of its own to Laravel, but what is published inside it belongs to the
     * one it sits in: grouping by the innermost would announce a subject once per savepoint.
     */
    private function outermostTransaction(): ?DatabaseTransactionRecord
    {
        $connection = $this->database->connection();

        if ($connection->transactionLevel() === 0 || ! $this->container->bound('db.transactions')) {
            return null;
        }

        $applicable = $this->container->make('db.transactions')->callbackApplicableTransactions();
        $name = $connection->getName();
        $record = $applicable->last(static fn (DatabaseTransactionRecord $record): bool => $record->connection === $name);

        while ($record instanceof DatabaseTransactionRecord && $record->parent instanceof DatabaseTransactionRecord && $applicable->contains($record->parent)) {
            $record = $record->parent;
        }

        return $record;
    }
}
