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
use Throwable;

/**
 * Dispatches ActivityRecorded once the transaction of the default connection commits (core#774), at
 * once outside one. Nothing while an impersonation lasts or inside its safety net: the change will be
 * rolled back, and nothing may leave the request. A listener's failure (a queue that cannot take the
 * job) is warned once a day, by its class, and never reaches the module that published.
 */
final readonly class EventActivityPublisher implements ActivityPublisherInterface
{
    private DailyWarnings $warnings;

    public function __construct(
        private Dispatcher $events,
        private DatabaseManager $database,
        private ImpersonationContextInterface $impersonation,
        private ImpersonationSafetyNet $safetyNet,
        Container $container,
    ) {
        $this->warnings = new DailyWarnings(
            ActivitySourceRegistry::WARNING_PREFIX,
            static fn (): Cache => $container->make(Cache::class),
        );
    }

    public function recorded(string $sourceType, string $subjectId): void
    {
        if ($this->impersonation->isActive() || $this->safetyNet->isEngaged()) {
            return;
        }

        if (! ActivitySourceType::tryFrom($sourceType) instanceof ActivitySourceType || ! ActivityIdentifierRule::isStableId($subjectId)) {
            // The type only when it is one: the id may be anything a module passed
            $this->warnings->warn('publisher|'.$sourceType, 'An activity was published with an invalid type or subject id; it was dropped.', [
                'type' => ActivitySourceType::tryFrom($sourceType)?->value,
                'reason' => 'invalid_publication',
            ]);

            return;
        }

        $event = new ActivityRecorded($sourceType, $subjectId);

        $this->database->connection()->afterCommit(function () use ($event): void {
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
}
