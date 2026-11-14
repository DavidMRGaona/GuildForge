<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteBlocks;

use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use App\Domain\SiteBlocks\ValueObjects\SiteBlockKey;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Container\Container;
use Throwable;

/**
 * Keeps the block classes registered by the core and the modules and builds them the first time a
 * block is asked for. It fails closed: a block that cannot be built, is not a SiteBlockInterface,
 * has an invalid key or one that is not its owner's, an invalid component path or placement, a key
 * another block also has, or (for a module block) settings that are not flat, is left out with a
 * warning once a day per class and reason (SiteBlockWarnings). Warnings name the class, the module,
 * the key and the reason, and an exception only by its class. A singleton built at boot.
 *
 * In php-fpm every request builds it again, so the blocks are built and checked once per request,
 * and only when something asks for a block. Checking a module block's settings builds its Filament
 * fields (about 70 µs for five fields); caching that across requests would need invalidating on
 * every core or module update and the blocks would still be built for isAvailable() and props().
 */
final class SiteBlockRegistry implements SiteBlockRegistryInterface
{
    /** Relative to the owner's resources/js, under components/, a .vue file, never climbing out */
    private const string COMPONENT_PATTERN = '#^components/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.vue$#';

    /** @var array<class-string, ?string> Block class => owning module (null for the core) */
    private array $blocks = [];

    /** @var array<string, array{block: SiteBlockInterface, placements: list<SiteBlockPlacement>, settingNames: list<string>|null}>|null Key => block */
    private ?array $resolved = null;

    private readonly SiteBlockWarnings $warnings;

    /**
     * @param  Cache|null  $cache  where warnings are remembered; the default store, asked for only when warning
     */
    public function __construct(
        private readonly Container $container,
        private readonly FlatSettingsSchemaRule $flatSettings = new FlatSettingsSchemaRule,
        ?Cache $cache = null,
    ) {
        $this->warnings = new SiteBlockWarnings(
            fn (): Cache => $cache ?? $this->container->make(Cache::class),
        );
    }

    public function register(string $blockClass, ?string $module = null): void
    {
        // A second owner of the same class would take over the first one's blocks
        if (array_key_exists($blockClass, $this->blocks)) {
            if ($this->blocks[$blockClass] !== $module) {
                $this->warnings->warn('registry|'.$blockClass.'|owner_conflict', 'A site block class is already registered by another owner; the second registration is ignored.', [
                    'class' => $blockClass,
                    'module' => $module,
                    'owner' => $this->blocks[$blockClass],
                    'reason' => 'owner_conflict',
                ]);
            }

            return;
        }

        $this->blocks[$blockClass] = $module;
        $this->resolved = null;
    }

    public function registerMany(array $blockClasses, ?string $module = null): void
    {
        foreach ($blockClasses as $blockClass) {
            $this->register($blockClass, $module);
        }
    }

    public function unregisterModule(string $module): void
    {
        $this->blocks = array_filter(
            $this->blocks,
            static fn (?string $owner): bool => $owner !== $module,
        );
        $this->resolved = null;
    }

    public function all(): array
    {
        return array_values(array_map(
            static fn (array $entry): SiteBlockInterface => $entry['block'],
            $this->resolveAll(),
        ));
    }

    public function forPlacement(SiteBlockPlacement $placement): array
    {
        $blocks = [];

        foreach ($this->resolveAll() as $entry) {
            if (in_array($placement, $entry['placements'], true)) {
                $blocks[] = $entry['block'];
            }
        }

        return $blocks;
    }

    public function find(string $key): ?SiteBlockInterface
    {
        return ($this->resolveAll()[$key] ?? null)['block'] ?? null;
    }

    public function settingNames(string $key): ?array
    {
        return ($this->resolveAll()[$key] ?? null)['settingNames'] ?? null;
    }

    /**
     * @return array<string, array{block: SiteBlockInterface, placements: list<SiteBlockPlacement>, settingNames: list<string>|null}>
     */
    private function resolveAll(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        /** @var array<string, list<array{block: SiteBlockInterface, placements: list<SiteBlockPlacement>, settingNames: list<string>|null, class: string}>> $byKey */
        $byKey = [];

        foreach ($this->blocks as $class => $module) {
            $built = $this->build($class, $module);
            if ($built !== null) {
                $byKey[$built['key']][] = [...$built, 'class' => $class];
            }
        }

        $resolved = [];

        foreach ($byKey as $key => $entries) {
            // Two blocks with one key: whichever won, the other's stored settings would draw it
            if (count($entries) > 1) {
                $this->warnings->warn('registry|'.$key.'|duplicate', 'Two site blocks have the same key; neither is offered.', [
                    'key' => $key,
                    'classes' => array_map(static fn (array $entry): string => $entry['class'], $entries),
                    'reason' => 'duplicate',
                ]);

                continue;
            }

            $resolved[$key] = [
                'block' => $entries[0]['block'],
                'placements' => $entries[0]['placements'],
                'settingNames' => $entries[0]['settingNames'],
            ];
        }

        return $this->resolved = $resolved;
    }

    /**
     * The block with its key, placements and (for a module block) setting names, read once, or null
     * (with a warning) when it cannot be offered.
     *
     * @return array{block: SiteBlockInterface, key: string, placements: list<SiteBlockPlacement>, settingNames: list<string>|null}|null
     */
    private function build(string $class, ?string $module): ?array
    {
        $context = ['class' => $class, 'module' => $module];

        try {
            $block = $this->container->make($class);

            if (! $block instanceof SiteBlockInterface) {
                return $this->refuse($class, 'type', 'A registered site block does not implement SiteBlockInterface; it is left out.', $context);
            }

            $key = SiteBlockKey::tryFrom($block->key());
            $context['key'] = $block->key();

            if (! $key instanceof SiteBlockKey) {
                return $this->refuse($class, 'key', 'A site block has an invalid key; it is left out.', $context);
            }

            // A module named `core`, or a block named after somebody else, would draw their stored blocks
            $owner = $module ?? SiteBlockKey::CORE_OWNER;
            if ($key->owner() !== $owner || $module === SiteBlockKey::CORE_OWNER) {
                return $this->refuse($class, 'owner', 'A site block key does not start with its module name (core. for the core); it is left out.', $context);
            }

            if (preg_match(self::COMPONENT_PATTERN, $block->component()) !== 1) {
                return $this->refuse($class, 'component', 'A site block has an invalid component path; it is left out.', $context);
            }

            // A module's code may not keep the promise of its PHPDoc
            /** @var array<mixed> $declared */
            $declared = $block->placements();
            $placements = [];
            foreach ($declared as $placement) {
                if (! $placement instanceof SiteBlockPlacement) {
                    return $this->refuse($class, 'placement', 'A site block has a placement that is not a SiteBlockPlacement; it is left out.', $context);
                }

                $placements[] = $placement;
            }

            $settingNames = null;
            if ($module !== null) {
                $settingNames = $this->flatSettings->names($block->settingsSchema());

                if ($settingNames === null) {
                    return $this->refuse($class, 'settings', 'A module site block has settings that are not flat fields; it is left out.', $context);
                }
            }
        } catch (Throwable $exception) {
            // Only the class: a module's message may carry what a person can see
            return $this->refuse($class, 'build', 'A site block could not be built; it is left out.', [
                ...$context,
                'exception' => $exception::class,
            ]);
        }

        return ['block' => $block, 'key' => $key->value, 'placements' => $placements, 'settingNames' => $settingNames];
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
