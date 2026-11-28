<?php

declare(strict_types=1);

namespace App\Infrastructure\Bots;

use App\Application\Bots\Contracts\BotInterface;
use App\Application\Bots\DTOs\BotDefinitionDTO;
use App\Application\Bots\Services\BotRegistryInterface;
use App\Domain\Bots\ValueObjects\BotKey;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Throwable;

/**
 * Keeps the bot classes the modules register and builds them the first time a bot is asked for.
 * It fails closed: a class that cannot be built, is not a BotInterface, has an invalid key or one
 * that is not its module's, or shares its key with another class is left out with a warning once
 * a day per class and reason (DailyWarnings). Warnings name class, module, key and reason, and an
 * exception only by its class. A singleton built at boot; classes are resolved once per request.
 */
final class BotRegistry implements BotRegistryInterface
{
    public const string WARNING_PREFIX = 'bots:warned:';

    /** @var array<class-string, string> Bot class => owning module */
    private array $bots = [];

    /** @var array<string, BotDefinitionDTO>|null Key => definition */
    private ?array $resolved = null;

    private readonly DailyWarnings $warnings;

    /**
     * @param  Cache|null  $cache  where warnings are remembered; the default store, asked for only when warning
     */
    public function __construct(
        private readonly Container $container,
        ?Cache $cache = null,
    ) {
        $this->warnings = new DailyWarnings(
            self::WARNING_PREFIX,
            fn (): Cache => $cache ?? $this->container->make(Cache::class),
        );
    }

    public function register(string $botClass, string $module): void
    {
        if (array_key_exists($botClass, $this->bots)) {
            if ($this->bots[$botClass] !== $module) {
                $this->warnings->warn('registry|'.$botClass.'|owner_conflict', 'A bot class is already registered by another module; the second registration is ignored.', [
                    'class' => $botClass,
                    'module' => $module,
                    'owner' => $this->bots[$botClass],
                    'reason' => 'owner_conflict',
                ]);
            }

            return;
        }

        $this->bots[$botClass] = $module;
        $this->resolved = null;
    }

    public function registerMany(array $botClasses, string $module): void
    {
        foreach ($botClasses as $botClass) {
            $this->register($botClass, $module);
        }
    }

    public function all(): array
    {
        return array_values($this->resolveAll());
    }

    public function find(string $key): ?BotDefinitionDTO
    {
        return $this->resolveAll()[$key] ?? null;
    }

    /**
     * @return array<string, BotDefinitionDTO>
     */
    private function resolveAll(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        /** @var array<string, list<BotDefinitionDTO>> $byKey */
        $byKey = [];

        foreach ($this->bots as $class => $module) {
            $definition = $this->build($class, $module);
            if ($definition instanceof BotDefinitionDTO) {
                $byKey[$definition->key][] = $definition;
            }
        }

        $resolved = [];

        foreach ($byKey as $key => $definitions) {
            // Two classes with one key: whichever won, the other would act as the same account
            if (count($definitions) > 1) {
                $this->warnings->warn('registry|'.$key.'|duplicate', 'Two bots have the same key; neither is offered.', [
                    'key' => $key,
                    'classes' => array_map(static fn (BotDefinitionDTO $definition): string => $definition->class, $definitions),
                    'reason' => 'duplicate',
                ]);

                continue;
            }

            $resolved[$key] = $definitions[0];
        }

        return $this->resolved = $resolved;
    }

    private function build(string $class, string $module): ?BotDefinitionDTO
    {
        $context = ['class' => $class, 'module' => $module];

        try {
            $bot = $this->container->make($class);

            if (! $bot instanceof BotInterface) {
                return $this->refuse($class, 'type', 'A registered bot does not implement BotInterface; it is left out.', $context);
            }

            $context['key'] = $bot->key();

            try {
                $key = BotKey::of($bot->key());
            } catch (InvalidArgumentException) {
                return $this->refuse($class, 'key', 'A bot has an invalid key; it is left out.', $context);
            }

            if ($key->module() !== $module) {
                return $this->refuse($class, 'owner', 'A bot key does not start with its module name; it is left out.', $context);
            }

            /** @var class-string<BotInterface> $class */
            return new BotDefinitionDTO(
                $key->value(),
                $module,
                $bot->label(),
                $bot->description(),
                $bot->name(),
                $class,
            );
        } catch (Throwable $exception) {
            // Only the class: a module's message may carry what a person can see
            return $this->refuse($class, 'build', 'A bot could not be built; it is left out.', [
                ...$context,
                'exception' => $exception::class,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function refuse(string $class, string $reason, string $message, array $context): null
    {
        $this->warnings->warn('registry|'.$class.'|'.$reason, $message, [...$context, 'reason' => $reason]);

        return null;
    }
}
