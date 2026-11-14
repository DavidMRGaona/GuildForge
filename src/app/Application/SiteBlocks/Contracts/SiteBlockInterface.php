<?php

declare(strict_types=1);

namespace App\Application\SiteBlocks\Contracts;

use App\Domain\SiteBlocks\SiteBlockPlacement;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * A block the administration can place on the public site: a column of the footer, the inside
 * of a popup (core#696). The core registers its own; a module returns its classes from
 * registerSiteBlocks(). Each one is built from the container, so it may take dependencies.
 *
 * The registry leaves a block out (with a warning) when its key is invalid or not its owner's,
 * its component path is invalid, a placement is not a SiteBlockPlacement, another block has the
 * same key, or, for a module block, its settings are not flat. Drawing it fails closed: an
 * unknown key, isAvailable() false or throwing, and props() throwing or not fit for the browser
 * all leave the block out of the page, which is never broken by it.
 */
interface SiteBlockInterface
{
    /**
     * `core.<block>` for the core, `<module>.<block>` for a module (its own name only). Stored
     * with the footer and the popups, so it never changes once offered.
     */
    public function key(): string;

    /**
     * The name shown in the panel when choosing a block, already translated.
     */
    public function label(): string;

    /**
     * Where the block may go.
     *
     * @return list<SiteBlockPlacement>
     */
    public function placements(): array;

    /**
     * The Filament fields of the block's settings (may be empty). Their values come back to props()
     * as `$settings`, keyed by field name.
     *
     * A module block may only use flat fields: TextInput (short text, number, link…), Select and
     * ToggleButtons with a single choice, Radio, Toggle and Checkbox, each named without dots.
     * No uploads, rich editors, repeaters or layouts: whatever lives in the footer's or a popup's
     * stored settings belongs to them, not to the module, so nobody could register those files or
     * editor images. A module block with any other field is left out.
     *
     * @return array<Component>
     */
    public function settingsSchema(): array;

    /**
     * The Vue component that draws the block, relative to the owner's `resources/js` and under
     * `components/` (e.g. `components/SubscribeBlock.vue`), resolved like a module slot. Core
     * blocks live in `components/siteBlocks/`.
     */
    public function component(): string;

    /**
     * Whether the block can be offered in the panel and drawn now (e.g. a provider is configured
     * and answering). Asked on every page that shows it, so keep it cheap; when it throws the block
     * is left out.
     */
    public function isAvailable(): bool;

    /**
     * The props for the component, for this person: only what they may see. A string-keyed array
     * that survives json_encode(); anything else leaves the block out. A module block only gets the
     * scalar values (string, int, float, bool or null) of the fields of its settingsSchema(); any
     * other stored key is dropped. Never pass settings on to the component wholesale: pick what it
     * needs. If this throws, only the exception class is logged, never its message.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    public function props(array $settings, ?Authenticatable $user): array;
}
