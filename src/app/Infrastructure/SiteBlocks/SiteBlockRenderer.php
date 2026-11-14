<?php

declare(strict_types=1);

namespace App\Infrastructure\SiteBlocks;

use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Application\SiteBlocks\DTOs\ResolvedSiteBlockDTO;
use App\Application\SiteBlocks\Services\SiteBlockRegistryInterface;
use App\Application\SiteBlocks\Services\SiteBlockRendererInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use App\Domain\SiteBlocks\ValueObjects\SiteBlockKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Cache\Repository as Cache;
use JsonException;
use Throwable;

/**
 * Resolves stored blocks to what the browser draws and fails closed (see the interface). The
 * footer is on every page, so a block that keeps failing would log on every request: each key and
 * reason is warned about once a day (SiteBlockWarnings). Warnings carry the key, the reason and an
 * exception only by its class; never settings, props or exception messages.
 */
final readonly class SiteBlockRenderer implements SiteBlockRendererInterface
{
    private SiteBlockWarnings $warnings;

    public function __construct(
        private SiteBlockRegistryInterface $registry,
        Cache $cache,
    ) {
        $this->warnings = new SiteBlockWarnings(static fn (): Cache => $cache);
    }

    public function resolveBlock(string $key, array $settings, SiteBlockPlacement $placement, ?Authenticatable $user): ?ResolvedSiteBlockDTO
    {
        $block = $this->registry->find($key);

        if (! $block instanceof SiteBlockInterface) {
            $this->warnOnce($key, 'unknown', 'A stored site block is not registered (its module may be disabled); it is not drawn.');

            return null;
        }

        try {
            if (! in_array($placement, $block->placements(), true)) {
                $this->warnOnce($key, 'placement', 'A stored site block is not offered for this placement; it is not drawn.', [
                    'placement' => $placement->value,
                ]);

                return null;
            }

            if (! $block->isAvailable()) {
                return null;
            }

            $owner = SiteBlockKey::tryFrom($key);
            $module = $owner instanceof SiteBlockKey && ! $owner->isCore() ? $owner->owner() : null;

            // A module's code may not keep the promise of its PHPDoc
            /** @var array<mixed> $returned */
            $returned = $block->props($module === null ? $settings : $this->flat($settings, $this->registry->settingNames($key) ?? []), $user);
            $props = $this->keyed($returned);

            if (count($props) !== count($returned)) {
                $this->warnOnce($key, 'props', 'A site block returned props that are not keyed by name; it is not drawn.');

                return null;
            }

            json_encode($props, JSON_THROW_ON_ERROR);

            return new ResolvedSiteBlockDTO($key, $module, $block->component(), $props);
        } catch (JsonException) {
            $this->warnOnce($key, 'json', 'A site block returned props that cannot be sent to the browser; it is not drawn.');
        } catch (Throwable $exception) {
            // Only the class: a module's message may carry what a person can see
            $this->warnOnce($key, 'failed', 'A site block failed; it is not drawn.', [
                'exception' => $exception::class,
            ]);
        }

        return null;
    }

    public function resolve(array $blocks, SiteBlockPlacement $placement, ?Authenticatable $user): array
    {
        $resolved = [];

        foreach ($blocks as $stored) {
            if (! is_array($stored) || ! is_string($stored['key'] ?? null) || ! is_array($stored['settings'] ?? [])) {
                $this->warnOnce('', 'malformed', 'A stored site block is malformed; it is not drawn.', [
                    'placement' => $placement->value,
                ]);

                continue;
            }

            $block = $this->resolveBlock($stored['key'], $this->keyed($stored['settings'] ?? []), $placement, $user);

            if ($block instanceof ResolvedSiteBlockDTO) {
                $resolved[] = $block;
            }
        }

        return $resolved;
    }

    public function available(SiteBlockPlacement $placement): array
    {
        $available = [];

        foreach ($this->registry->forPlacement($placement) as $block) {
            try {
                if ($block->isAvailable() === true) {
                    $available[] = $block;
                }
            } catch (Throwable $exception) {
                $this->warnOnce($this->keyOf($block), 'availability', 'A site block failed its availability check; it is not offered.', [
                    'exception' => $exception::class,
                ]);
            }
        }

        return $available;
    }

    /**
     * Only the plain values of the block's own fields: a key no field has (forged into the stored
     * JSON, say `innerHTML`) or a value its flat fields never write is dropped, so nothing else can
     * reach props() and, through a module that passes settings on, the component's attributes.
     *
     * @param  array<string, mixed>  $settings
     * @param  list<string>  $names
     * @return array<string, string|int|float|bool|null>
     */
    private function flat(array $settings, array $names): array
    {
        return array_filter(
            array_intersect_key($settings, array_flip($names)),
            static fn (mixed $value): bool => $value === null || is_scalar($value),
        );
    }

    /**
     * @param  array<mixed>  $settings
     * @return array<string, mixed>
     */
    private function keyed(array $settings): array
    {
        return array_filter($settings, is_string(...), ARRAY_FILTER_USE_KEY);
    }

    private function keyOf(SiteBlockInterface $block): string
    {
        try {
            return $block->key();
        } catch (Throwable) {
            return $block::class;
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function warnOnce(string $key, string $reason, string $message, array $context = []): void
    {
        $this->warnings->warn('renderer|'.$key.'|'.$reason, $message, [
            'key' => mb_substr($key, 0, SiteBlockKey::MAX_LENGTH),
            'reason' => $reason,
            ...$context,
        ]);
    }
}
