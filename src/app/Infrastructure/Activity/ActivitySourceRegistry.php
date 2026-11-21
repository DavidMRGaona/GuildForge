<?php

declare(strict_types=1);

namespace App\Infrastructure\Activity;

use App\Application\Activity\Contracts\ActivitySourceInterface;
use App\Application\Activity\DTOs\ActivitySourceRegistrationDTO;
use App\Application\Activity\Services\ActivitySourceRegistryInterface;
use App\Domain\Activity\ValueObjects\ActivitySourceType;
use App\Infrastructure\Support\DailyWarnings;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Activity sources registered by the core and the modules (core#774), each built once the first
 * time one is asked for, only to check it and read its type and kind. It keeps what it read and the
 * class, never the instance: it is a singleton, and an instance kept here would carry what it read
 * in one request or job into the next. It fails closed: a class that cannot be built, is not a
 * source, has an invalid type or one that is not its owner's, or a type another one also has, is
 * left out with a warning once a day per class and reason. Warnings name the class, the module, the
 * type and the reason, and an exception only by its class.
 */
final class ActivitySourceRegistry implements ActivitySourceRegistryInterface
{
    /** Where the registry's, the reader's and the publisher's warnings are remembered for a day */
    public const string WARNING_PREFIX = 'activity:warned:';

    /** @var array<string, ?string> Class (or container id) => owning module (null for the core) */
    private array $classes = [];

    /** @var array<string, ActivitySourceRegistrationDTO>|null Type => registration, sorted by type */
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

    public function register(string $class, ?string $module = null): void
    {
        // A second owner of the same class would take over the first one's type
        if (array_key_exists($class, $this->classes)) {
            if ($this->classes[$class] !== $module) {
                $this->warnings->warn('registry|'.$class.'|owner_conflict', 'An activity source class is already registered by another owner; the second registration is ignored.', [
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

    public function all(): array
    {
        return array_values($this->resolveAll());
    }

    public function find(string $type): ?ActivitySourceRegistrationDTO
    {
        return $this->resolveAll()[$type] ?? null;
    }

    /**
     * @return array<string, ActivitySourceRegistrationDTO>
     */
    private function resolveAll(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        /** @var array<string, list<ActivitySourceRegistrationDTO>> $byType */
        $byType = [];

        foreach ($this->classes as $class => $module) {
            $registration = $this->build($class, $module);
            if ($registration instanceof ActivitySourceRegistrationDTO) {
                $byType[$registration->type][] = $registration;
            }
        }

        $resolved = [];

        foreach ($byType as $type => $registrations) {
            // Two with one type: whichever won, a consumer's record of the other would be read from it
            if (count($registrations) > 1) {
                $this->warnings->warn('registry|'.$type.'|duplicate', 'Two activity sources have the same type; neither is offered.', [
                    'type' => $type,
                    'classes' => array_map(static fn (ActivitySourceRegistrationDTO $registration): string => $registration->class, $registrations),
                    'reason' => 'duplicate',
                ]);

                continue;
            }

            $resolved[$type] = $registrations[0];
        }

        ksort($resolved, SORT_STRING);

        return $this->resolved = $resolved;
    }

    /**
     * The type and kind of the class, read once from an instance built only for this, or null (with
     * a warning) when it cannot be offered.
     */
    private function build(string $class, ?string $module): ?ActivitySourceRegistrationDTO
    {
        $context = ['class' => $class, 'module' => $module];

        try {
            $source = $this->container->make($class);

            if (! $source instanceof ActivitySourceInterface) {
                return $this->refuse($class, 'type', 'A registered activity source does not implement ActivitySourceInterface; it is left out.', $context);
            }

            $context['type'] = $source->type();
            $type = ActivitySourceType::tryFrom($source->type());

            if (! $type instanceof ActivitySourceType) {
                return $this->refuse($class, 'invalid_type', 'An activity source has an invalid type; it is left out.', $context);
            }

            // A module named `core`, or a type named after somebody else, would take over their records
            $owner = $module ?? ActivitySourceType::CORE_OWNER;
            if ($type->owner() !== $owner || $module === ActivitySourceType::CORE_OWNER) {
                return $this->refuse($class, 'owner', 'An activity source type does not start with its module name (core. for the core); it is left out.', $context);
            }

            $kind = $source->kind();
        } catch (Throwable $exception) {
            // Only the class: a module's message may carry what a person can see
            return $this->refuse($class, 'build', 'An activity source could not be built; it is left out.', [
                ...$context,
                'exception' => $exception::class,
            ]);
        }

        return new ActivitySourceRegistrationDTO($type->value, $kind, $module, $class);
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
