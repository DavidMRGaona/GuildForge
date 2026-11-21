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
 * Activity sources registered by the core and the modules (core#774), each built the first time one
 * is asked for, only to check it and read its type and kind. It keeps what it read and the class,
 * never the instance: it is a singleton, and an instance kept here would carry what it read in one
 * request or job into the next. It fails closed: a class that is not a source, has an invalid type
 * or one that is not its owner's is refused for good; a type another one also has is offered by
 * neither; a class that throws is refused only this time and built again on the next lookup, so a
 * passing failure in a long-running worker does not last until it restarts. Warnings (once a day per
 * class and reason) name the class, the module, the type and the reason, and an exception only by
 * its class.
 */
final class ActivitySourceRegistry implements ActivitySourceRegistryInterface
{
    /** Where the registry's, the reader's and the publisher's warnings are remembered for a day */
    public const string WARNING_PREFIX = 'activity:warned:';

    /** @var array<string, ?string> Class (or container id) => owning module (null for the core) */
    private array $classes = [];

    /** @var array<string, ActivitySourceRegistrationDTO|false> Class => what it read, or false when refused for good */
    private array $built = [];

    /** @var array<string, ActivitySourceRegistrationDTO>|null Type => registration, sorted by type; null until resolved */
    private ?array $offered = null;

    /** @var array<string, true> Types registered but not offered now: duplicated, or a class that failed */
    private array $unavailable = [];

    /** Whether a class failed before its type could be read */
    private bool $unbuildable = false;

    /** @var array<string, ActivitySourceRegistrationDTO>|null What the latest lookup offered, cached or not */
    private ?array $latest = null;

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
        $this->offered = $this->latest = null;
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
        $this->built = array_intersect_key($this->built, $this->classes);
        $this->offered = $this->latest = null;
    }

    public function all(): array
    {
        return array_values($this->resolveAll());
    }

    public function find(string $type): ?ActivitySourceRegistrationDTO
    {
        return $this->resolveAll()[$type] ?? null;
    }

    public function isUnavailable(string $type): bool
    {
        // The latest lookup's answer: resolving again would build a failing class once more and
        // could contradict the find() just made
        $offered = $this->latest ?? $this->resolveAll();

        if (isset($offered[$type])) {
            return false;
        }

        return isset($this->unavailable[$type]) || $this->unbuildable;
    }

    /**
     * @return array<string, ActivitySourceRegistrationDTO>
     */
    private function resolveAll(): array
    {
        if ($this->offered !== null) {
            return $this->offered;
        }

        /** @var array<string, list<ActivitySourceRegistrationDTO>> $byType */
        $byType = [];
        /** @var array<string, true> $failing types read by a class that then threw */
        $failing = [];
        $unbuildable = false;

        foreach ($this->classes as $class => $module) {
            $outcome = $this->built[$class] ?? $this->build($class, $module);

            if ($outcome instanceof ActivitySourceRegistrationDTO) {
                $this->built[$class] = $outcome;
                $byType[$outcome->type][] = $outcome;
            } elseif ($outcome === false) {
                $this->built[$class] = false;
            } elseif (is_string($outcome)) {
                // It threw after its type was read: that type is unknown now, not gone
                $failing[$outcome] = true;
            } else {
                $unbuildable = true;
            }
        }

        $offered = [];
        $unavailable = $failing;

        foreach ($byType as $type => $registrations) {
            // Two with one type (one of them failing now counts too: once it builds they clash):
            // whichever won, a consumer's record of the other would be read from it
            if (isset($failing[$type])) {
                continue;
            }

            if (count($registrations) > 1) {
                $this->warnings->warn('registry|'.$type.'|duplicate', 'Two activity sources have the same type; neither is offered.', [
                    'type' => $type,
                    'classes' => array_map(static fn (ActivitySourceRegistrationDTO $registration): string => $registration->class, $registrations),
                    'reason' => 'duplicate',
                ]);
                $unavailable[$type] = true;

                continue;
            }

            $offered[$type] = $registrations[0];
        }

        ksort($offered, SORT_STRING);
        $this->unavailable = $unavailable;
        $this->unbuildable = $unbuildable;

        // A passing failure is never remembered: the next lookup builds that class again
        if (! $unbuildable && $failing === []) {
            $this->offered = $offered;
        }

        return $this->latest = $offered;
    }

    /**
     * What an instance built only for this reads: its registration, false when it is refused for
     * good (not a source, an invalid or foreign type), or, when it throws, the type it had read
     * (null before that), so the failure is retried.
     */
    private function build(string $class, ?string $module): ActivitySourceRegistrationDTO|false|string|null
    {
        $context = ['class' => $class, 'module' => $module];
        $readType = null;

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

            $readType = $type->value;
            $kind = $source->kind();
        } catch (Throwable $exception) {
            // Only the class: a module's message may carry what a person can see
            $this->warnings->warn('registry|'.$class.'|build', 'An activity source could not be built; it is tried again on the next lookup.', [
                ...$context,
                'reason' => 'build',
                'exception' => $exception::class,
            ]);

            return $readType;
        }

        return new ActivitySourceRegistrationDTO($type->value, $kind, $module, $class);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function refuse(string $class, string $reason, string $message, array $context): false
    {
        $this->warnings->warn('registry|'.$class.'|'.$reason, $message, [...$context, 'reason' => $reason]);

        return false;
    }
}
