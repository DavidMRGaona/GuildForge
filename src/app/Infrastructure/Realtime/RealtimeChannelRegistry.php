<?php

declare(strict_types=1);

namespace App\Infrastructure\Realtime;

use App\Application\Realtime\Contracts\RealtimeChannelInterface;
use App\Application\Realtime\Services\RealtimeChannelRegistryInterface;
use App\Domain\Realtime\ValueObjects\RealtimeChannelPattern;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps the channel classes registered by the core and the modules, builds them the first time a
 * subscription is checked, and answers that check. It fails closed: a channel that cannot be
 * built or whose name is not its own is left out; an unknown or ambiguous channel, or a check that
 * throws, refuses the subscription. A singleton built at boot, so it logs through the facade.
 */
final class RealtimeChannelRegistry implements RealtimeChannelRegistryInterface
{
    /** The prefix of the core's own channels; module channels start with the module name. */
    public const string CORE_PREFIX = 'core';

    /** @var array<class-string, ?string> Channel class => owning module (null for the core) */
    private array $channels = [];

    /** @var list<array{pattern: RealtimeChannelPattern, channel: RealtimeChannelInterface, module: ?string}>|null */
    private ?array $resolved = null;

    /** @var array<string, true> */
    private array $warned = [];

    public function __construct(
        private readonly Container $container,
    ) {}

    public function register(string $channelClass, ?string $module = null): void
    {
        $this->channels[$channelClass] = $module;
        $this->resolved = null;
    }

    public function registerMany(array $channelClasses, ?string $module = null): void
    {
        foreach ($channelClasses as $channelClass) {
            $this->register($channelClass, $module);
        }
    }

    public function unregisterModule(string $module): void
    {
        $this->channels = array_filter(
            $this->channels,
            static fn (?string $owner): bool => $owner !== $module,
        );
        $this->resolved = null;
    }

    public function authorize(object $user, string $channel): bool
    {
        $matches = [];

        foreach ($this->resolveAll() as $entry) {
            $parameters = $entry['pattern']->match($channel);
            if ($parameters !== null) {
                $matches[] = [$entry, $parameters];
            }
        }

        if (count($matches) !== 1) {
            if (count($matches) > 1) {
                Log::warning('A real-time channel matches more than one registered channel; nobody may listen to it.', [
                    'channel' => $channel,
                    'patterns' => array_map(static fn (array $match): string => $match[0]['pattern']->value, $matches),
                ]);
            }

            return false;
        }

        [$entry, $parameters] = $matches[0];

        try {
            return $entry['channel']->authorize($user, $parameters);
        } catch (Throwable $exception) {
            Log::warning('A real-time channel check failed; the subscription was refused.', [
                'channel' => $channel,
                'module' => $entry['module'],
                'exception' => $exception->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * @return list<array{pattern: RealtimeChannelPattern, channel: RealtimeChannelInterface, module: ?string}>
     */
    private function resolveAll(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        $resolved = [];

        foreach ($this->channels as $class => $module) {
            try {
                $channel = $this->container->make($class);
            } catch (Throwable $exception) {
                $this->warnOnce($class, 'build', 'A real-time channel could not be built; it is left out.', [
                    'class' => $class,
                    'module' => $module,
                    'exception' => $exception->getMessage(),
                ]);

                continue;
            }

            if (! $channel instanceof RealtimeChannelInterface) {
                $this->warnOnce($class, 'type', 'A registered real-time channel does not implement RealtimeChannelInterface; it is left out.', [
                    'class' => $class,
                    'module' => $module,
                ]);

                continue;
            }

            $pattern = RealtimeChannelPattern::tryFrom($channel->name());
            if (! $pattern instanceof RealtimeChannelPattern) {
                $this->warnOnce($class, 'name', 'A real-time channel has an invalid name; it is left out.', [
                    'class' => $class,
                    'module' => $module,
                    'name' => $channel->name(),
                ]);

                continue;
            }

            // A channel named after somebody else would let its check grant their channel
            if ($pattern->prefix() !== ($module ?? self::CORE_PREFIX)) {
                $this->warnOnce($class, 'prefix', 'A real-time channel does not start with its module name (core. for the core); it is left out.', [
                    'class' => $class,
                    'module' => $module,
                    'name' => $pattern->value,
                ]);

                continue;
            }

            $resolved[] = ['pattern' => $pattern, 'channel' => $channel, 'module' => $module];
        }

        return $this->resolved = $resolved;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function warnOnce(string $class, string $kind, string $message, array $context): void
    {
        if (isset($this->warned[$class.'|'.$kind])) {
            return;
        }

        $this->warned[$class.'|'.$kind] = true;
        Log::warning($message, $context);
    }
}
