<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles;

use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\DTOs\ProfileRegistrationDTO;
use App\Domain\Profiles\ValueObjects\ProfileKey;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * What the section and stat registries share (core#742): classes registered by the core and the
 * modules, each built once the first time one is asked for, only to check it and read its key,
 * default order and default on/off. The registry keeps what it read and the class (ProfileRegistrationDTO), never the
 * instance: it is a singleton, and an instance kept here would carry what it read in one request or
 * job (a scoped service, a row) into the next. Whoever draws them builds them again in its own scope. It fails closed: a class that
 * cannot be built, is not of the expected interface, has an invalid key or one that is not its
 * owner's, fails a check of its own kind, or has a key another one also has, is left out with a
 * warning once a day per class and reason (DailyWarnings). Warnings name the class, the module, the
 * key and the reason, and an exception only by its class. A singleton built at boot; in php-fpm every
 * request builds it again, so each one is built and checked at most once per request.
 *
 * @template T of ProfileSectionInterface|ProfileStatInterface
 */
abstract class ProfileContributionRegistry
{
    /** Where the registries' and the renderer's warnings are remembered for a day */
    public const string WARNING_PREFIX = 'profiles:warned:';

    /** @var array<string, ?string> Class (or container id) => owning module (null for the core) */
    private array $classes = [];

    /** @var array<string, ProfileRegistrationDTO>|null Key => registration, by default order then key */
    private ?array $resolved = null;

    protected readonly DailyWarnings $warnings;

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

    public function register(string $class, ?string $module = null): void
    {
        // A second owner of the same class would take over the first one's keys
        if (array_key_exists($class, $this->classes)) {
            if ($this->classes[$class] !== $module) {
                $this->warnings->warn($this->kind().'-registry|'.$class.'|owner_conflict', sprintf('A profile %s class is already registered by another owner; the second registration is ignored.', $this->kind()), [
                    'class' => $class,
                    'module' => $module,
                    'owner' => $this->classes[$class],
                    'reason' => 'owner_conflict',
                ]);
            }

            return;
        }

        $this->classes[$class] = $module;
        $this->resolved = null;
    }

    /**
     * @param  array<string>  $classes
     */
    public function registerMany(array $classes, ?string $module = null): void
    {
        foreach ($classes as $class) {
            $this->register($class, $module);
        }
    }

    public function unregisterModule(string $module): void
    {
        $this->classes = array_filter(
            $this->classes,
            static fn (?string $owner): bool => $owner !== $module,
        );
        $this->resolved = null;
    }

    /**
     * @return list<ProfileRegistrationDTO>
     */
    public function all(): array
    {
        return array_values($this->resolveAll());
    }

    public function find(string $key): ?ProfileRegistrationDTO
    {
        return $this->resolveAll()[$key] ?? null;
    }

    /** `section` or `stat`, for warnings */
    abstract protected function kind(): string;

    /**
     * The interface every registered class must implement.
     *
     * @return class-string<T>
     */
    abstract protected function contract(): string;

    /**
     * The checks of this kind, beyond the key: null when it passes, else the reason it is left out.
     * May throw: that leaves it out too.
     *
     * @param  T  $item
     */
    abstract protected function refusalOf(object $item): ?string;

    /**
     * @return array<string, ProfileRegistrationDTO>
     */
    private function resolveAll(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        /** @var array<string, list<array{key: string, order: int, enabledByDefault: bool, module: string|null, class: string}>> $byKey */
        $byKey = [];

        foreach ($this->classes as $class => $module) {
            $built = $this->build($class, $module);
            if ($built !== null) {
                $byKey[$built['key']][] = [...$built, 'module' => $module, 'class' => $class];
            }
        }

        /** @var list<ProfileRegistrationDTO> $valid */
        $valid = [];

        foreach ($byKey as $key => $entries) {
            // Two with one key: whichever won, the owners' choices for the other would apply to it
            if (count($entries) > 1) {
                $this->warnings->warn($this->kind().'-registry|'.$key.'|duplicate', sprintf('Two profile %ss have the same key; neither is offered.', $this->kind()), [
                    'key' => $key,
                    'classes' => array_map(static fn (array $entry): string => $entry['class'], $entries),
                    'reason' => 'duplicate',
                ]);

                continue;
            }

            $valid[] = new ProfileRegistrationDTO($key, $entries[0]['module'], $entries[0]['class'], $entries[0]['order'], $entries[0]['enabledByDefault']);
        }

        usort($valid, static fn (ProfileRegistrationDTO $a, ProfileRegistrationDTO $b): int => [$a->order, $a->key] <=> [$b->order, $b->key]);

        $resolved = [];
        foreach ($valid as $registration) {
            $resolved[$registration->key] = $registration;
        }

        return $this->resolved = $resolved;
    }

    /**
     * The key, order and default of the class, read once from an instance built only for this, or null
     * (with a warning) when it cannot be offered.
     *
     * @return array{key: string, order: int, enabledByDefault: bool}|null
     */
    private function build(string $class, ?string $module): ?array
    {
        $context = ['class' => $class, 'module' => $module];

        try {
            $item = $this->container->make($class);
            $contract = $this->contract();

            if (! $item instanceof $contract) {
                return $this->refuse($class, 'type', sprintf('A registered profile %s does not implement %s; it is left out.', $this->kind(), class_basename($contract)), $context);
            }

            $context['key'] = $item->key();
            $key = ProfileKey::tryFrom($item->key());

            if (! $key instanceof ProfileKey) {
                return $this->refuse($class, 'key', sprintf('A profile %s has an invalid key; it is left out.', $this->kind()), $context);
            }

            // A module named `core`, or a key named after somebody else, would take over their layouts
            $owner = $module ?? ProfileKey::CORE_OWNER;
            if ($key->owner() !== $owner || $module === ProfileKey::CORE_OWNER) {
                return $this->refuse($class, 'owner', sprintf('A profile %s key does not start with its module name (core. for the core); it is left out.', $this->kind()), $context);
            }

            $reason = $this->refusalOf($item);
            if ($reason !== null) {
                return $this->refuse($class, $reason, sprintf('A profile %s failed its %s check; it is left out.', $this->kind(), $reason), $context);
            }

            $order = $item->order();
            $enabledByDefault = $item->enabledByDefault();
        } catch (Throwable $exception) {
            // Only the class: a module's message may carry what a person can see
            return $this->refuse($class, 'build', sprintf('A profile %s could not be built; it is left out.', $this->kind()), [
                ...$context,
                'exception' => $exception::class,
            ]);
        }

        return ['key' => $key->value, 'order' => $order, 'enabledByDefault' => $enabledByDefault];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function refuse(string $class, string $reason, string $message, array $context): null
    {
        $this->warnings->warn($this->kind().'-registry|'.$class.'|'.$reason, $message, [...$context, 'reason' => $reason]);

        return null;
    }
}
