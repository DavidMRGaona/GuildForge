<?php

declare(strict_types=1);

namespace App\Infrastructure\Profiles;

use App\Application\Profiles\DTOs\ProfileBadgeSourceRegistrationDTO;
use App\Application\Profiles\Services\ProfileBadgeSourceRegistryInterface;
use App\Infrastructure\Support\DailyWarnings;
use Closure;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * The profile badge sources of the core and the modules (core#776). A singleton filled while modules
 * boot, keeping classes and owners only, never instances: ProfileBadgeCollector builds each source
 * in the request or job that asks for badges, so nothing one request read reaches the next.
 */
final class ProfileBadgeSourceRegistry implements ProfileBadgeSourceRegistryInterface
{
    /** @var array<string, ?string> Class (or container id) => owning module (null for the core) */
    private array $classes = [];

    private readonly DailyWarnings $warnings;

    /**
     * @param  Cache|(Closure(): Cache)  $cache  where warnings are remembered, asked for only when warning
     */
    public function __construct(Cache|Closure $cache)
    {
        $this->warnings = new DailyWarnings(
            ProfileContributionRegistry::WARNING_PREFIX,
            $cache instanceof Cache ? static fn (): Cache => $cache : $cache,
        );
    }

    public function register(string $sourceClass, ?string $module = null): void
    {
        if (array_key_exists($sourceClass, $this->classes)) {
            // A second owner would give the first one's badges under another module's name
            if ($this->classes[$sourceClass] !== $module) {
                $this->warnings->warn('badge-registry|'.$sourceClass.'|owner_conflict', 'A profile badge source class is already registered by another owner; the second registration is ignored.', [
                    'class' => $sourceClass,
                    'module' => $module,
                    'owner' => $this->classes[$sourceClass],
                    'reason' => 'owner_conflict',
                ]);
            }

            return;
        }

        $this->classes[$sourceClass] = $module;
    }

    public function registerMany(array $sourceClasses, ?string $module = null): void
    {
        foreach ($sourceClasses as $sourceClass) {
            $this->register($sourceClass, $module);
        }
    }

    public function unregisterModule(string $module): void
    {
        $this->classes = array_filter(
            $this->classes,
            static fn (?string $owner): bool => $owner !== $module,
        );
    }

    public function all(): array
    {
        $all = [];
        foreach ($this->classes as $class => $module) {
            $all[] = new ProfileBadgeSourceRegistrationDTO($class, $module);
        }

        return $all;
    }
}
