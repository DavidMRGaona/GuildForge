<?php

declare(strict_types=1);

namespace App\Application\SiteBlocks\Services;

use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Application\SiteBlocks\DTOs\ResolvedSiteBlockDTO;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Turns the blocks a page stored (footer columns, a popup) into what the browser draws, for one
 * person (core#696). It fails closed and never throws: a block that cannot be drawn is left out,
 * and anything unexpected (unknown key, a check or props() that throws, props not fit for the
 * browser) is logged once per key and reason for a day. Settings and props are never logged.
 */
interface SiteBlockRendererInterface
{
    /**
     * One stored block, or null when it cannot be drawn now: unknown key (e.g. its module is
     * disabled), not offered for this placement, not available, or failing.
     *
     * @param  array<string, mixed>  $settings
     */
    public function resolveBlock(string $key, array $settings, SiteBlockPlacement $placement, ?Authenticatable $user): ?ResolvedSiteBlockDTO;

    /**
     * A list of stored blocks (`['key' => …, 'settings' => [...]]`, other entries ignored), in order,
     * without the ones that cannot be drawn. Malformed entries are left out too.
     *
     * @param  array<mixed>  $blocks
     * @return list<ResolvedSiteBlockDTO>
     */
    public function resolve(array $blocks, SiteBlockPlacement $placement, ?Authenticatable $user): array;

    /**
     * The blocks the panel may offer for a placement: valid and available now. A block whose
     * isAvailable() throws is not offered.
     *
     * @return list<SiteBlockInterface>
     */
    public function available(SiteBlockPlacement $placement): array;
}
