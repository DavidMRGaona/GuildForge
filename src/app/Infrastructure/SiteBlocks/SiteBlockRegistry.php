<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteBlocks;

use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use App\Domain\SiteBlocks\ValueObjects\SiteBlockKey;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Keeps the block classes registered by the core and the modules and builds them the first time a
 * block is asked for. It fails closed: a block that cannot be built, is not a SiteBlockInterface,
 * has an invalid key or one that is not its owner's, an invalid component path or placement, a key
 * another block also has, or (for a module block) settings that are not flat, is left out with a
 * warning once per process. A singleton built at boot, so it logs through the facade.
 */
final class SiteBlockRegistry implements SiteBlockRegistryInterface
{
    /** Relative to the owner's resources/js, under components/, a .vue file, never climbing out */
    private const string COMPONENT_PATTERN = '#^components/(?:[A-Za-z0-9_-]+/)*[A-Za-z0-9_-]+\.vue$#';

    /** @var array<class-string, ?string> Block class => owning module (null for the core) */
    private array $blocks = [];

    /** @var array<string, array{block: SiteBlockInterface, placements: list<SiteBlockPlacement>}>|null Key => block */
    private ?array $resolved = null;

    /** @var array<string, true> */
    private array $warned = [];

    public function __construct(
        private readonly Container $container,
        private readonly FlatSettingsSchemaRule $flatSettings = new FlatSettingsSchemaRule,
    ) {}

    public function register(string $blockClass, ?string $module = null): void
    {
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

    /**
     * @return array<string, array{block: SiteBlockInterface, placements: list<SiteBlockPlacement>}>
     */
    private function resolveAll(): array
    {
        if ($this->resolved !== null) {
            return $this->resolved;
        }

        /** @var array<string, list<array{block: SiteBlockInterface, placements: list<SiteBlockPlacement>, class: string}>> $byKey */
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
                $this->warnOnce('key:'.$key, 'Two site blocks have the same key; neither is offered.', [
                    'key' => $key,
                    'classes' => array_map(static fn (array $entry): string => $entry['class'], $entries),
                ]);

                continue;
            }

            $resolved[$key] = ['block' => $entries[0]['block'], 'placements' => $entries[0]['placements']];
        }

        return $this->resolved = $resolved;
    }

    /**
     * The block with its key and placements, read once, or null (with a warning) when it cannot
     * be offered.
     *
     * @return array{block: SiteBlockInterface, key: string, placements: list<SiteBlockPlacement>}|null
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

            if ($module !== null && ! $this->flatSettings->allows($block->settingsSchema())) {
                return $this->refuse($class, 'settings', 'A module site block has settings that are not flat fields; it is left out.', $context);
            }
        } catch (Throwable $exception) {
            return $this->refuse($class, 'build', 'A site block could not be built; it is left out.', [
                ...$context,
                'exception' => $exception->getMessage(),
            ]);
        }

        return ['block' => $block, 'key' => $key->value, 'placements' => $placements];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function refuse(string $class, string $reason, string $message, array $context): null
    {
        $this->warnOnce($class.'|'.$reason, $message, $context);

        return null;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function warnOnce(string $id, string $message, array $context): void
    {
        if (isset($this->warned[$id])) {
            return;
        }

        $this->warned[$id] = true;
        Log::warning($message, $context);
    }
}
