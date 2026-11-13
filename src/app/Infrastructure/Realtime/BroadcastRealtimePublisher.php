<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

use App\Application\Impersonation\Services\ImpersonationContextInterface;
use App\Application\Realtime\Services\RealtimeConfigurationInterface;
use App\Application\Realtime\Services\RealtimePublisherInterface;
use App\Domain\Realtime\RealtimeMessageRule;
use App\Domain\Realtime\ValueObjects\RealtimeChannelPattern;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\DatabaseManager;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Publishes through Laravel broadcasting (the `reverb` connection), after the transaction of the
 * default database connection commits.
 */
final readonly class BroadcastRealtimePublisher implements RealtimePublisherInterface
{
    public function __construct(
        private RealtimeConfigurationInterface $configuration,
        private Dispatcher $events,
        private DatabaseManager $database,
        private LoggerInterface $logger,
        private ImpersonationContextInterface $impersonation,
    ) {}

    public function publish(string $channel, string $event, array $payload = []): void
    {
        // Nothing leaves while an impersonation lasts (core#572), even outside a transaction
        if (! $this->configuration->enabled() || $this->impersonation->isActive()) {
            return;
        }

        if (! RealtimeChannelPattern::isChannelName($channel) || ! RealtimeMessageRule::isValidEventName($event)) {
            $this->logger->warning('A real-time message with an invalid channel or event name was not sent.', [
                'channel' => $channel,
                'event' => $event,
            ]);

            return;
        }

        if (! RealtimeMessageRule::isValidPayload($payload)) {
            // The payload itself is never logged: it is what must not travel
            $this->logger->warning('A real-time message was not sent: its payload may only hold ids and numbers.', [
                'channel' => $channel,
                'event' => $event,
                'keys' => array_map(strval(...), array_keys($payload)),
            ]);

            return;
        }

        $message = new RealtimeBroadcast($channel, $event, $payload);

        $this->database->connection()->afterCommit(function () use ($message): void {
            try {
                $this->events->dispatch($message);
            } catch (Throwable $exception) {
                $this->logger->warning('A real-time message could not be sent; the pages catch up with their own requests.', [
                    'channel' => $message->channel,
                    'event' => $message->name,
                    'exception' => $exception->getMessage(),
                ]);
            }
        });
    }
}
