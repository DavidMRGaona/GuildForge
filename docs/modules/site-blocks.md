# Site blocks

A site block is a piece of the public site that the administration places where it wants: a column of the configurable footer or the inside of a popup of the announcements module. The core and the modules offer blocks; the footer and the popups store which ones were chosen with their settings, and ask the core to draw them for each person. No module depends on another: a popup can hold a newsletter block without either module knowing the other.

Requires core ≥ the release with #696; set `requires.core` to it.

## Contents

1. [Offering a block](#offering-a-block)
2. [The contract](#the-contract)
3. [Flat settings](#flat-settings)
4. [Drawing the blocks of a placement](#drawing-the-blocks-of-a-placement)
5. [Failing closed](#failing-closed)
6. [Testing](#testing)

## Offering a block

Implement `App\Application\SiteBlocks\Contracts\SiteBlockInterface` and return the class from the provider's `registerSiteBlocks()`. Blocks are built from the container, so they may take dependencies in their constructor.

```php
use App\Application\SiteBlocks\Contracts\SiteBlockInterface;
use App\Domain\SiteBlocks\SiteBlockPlacement;
use Filament\Forms\Components\TextInput;
use Illuminate\Contracts\Auth\Authenticatable;

final class SubscribeSiteBlock implements SiteBlockInterface
{
    public function __construct(
        private readonly NewsletterAvailability $availability,
    ) {}

    public function key(): string
    {
        return 'newsletter.subscribe';
    }

    public function label(): string
    {
        return __('newsletter::messages.blocks.subscribe');
    }

    public function placements(): array
    {
        return [SiteBlockPlacement::Footer, SiteBlockPlacement::Popup];
    }

    public function settingsSchema(): array
    {
        return [
            TextInput::make('heading')->label(__('newsletter::messages.blocks.heading'))->maxLength(80),
        ];
    }

    public function component(): string
    {
        return 'components/SubscribeBlock.vue';
    }

    public function isAvailable(): bool
    {
        return $this->availability->isAvailable();
    }

    public function props(array $settings, ?Authenticatable $user): array
    {
        return [
            'heading' => is_string($settings['heading'] ?? null) ? $settings['heading'] : null,
        ];
    }
}
```

```php
// NewsletterServiceProvider
public function registerSiteBlocks(): array
{
    return [SubscribeSiteBlock::class];
}
```

The component lives in the module's `resources/js/components/SubscribeBlock.vue` and receives the props as its own. It is loaded like a module slot component (the module's Vite build in production), so build it with the module.

## The contract

| Method | What it returns |
|---|---|
| `key()` | `<module>.<block>` (the module's own name only) or `core.<block>` for the core. The owner is kebab-case, the block name snake_case or kebab-case, 100 characters at most. It is stored with the footer and the popups: never change it once a block is offered |
| `label()` | The name shown in the panel when choosing a block, translated |
| `placements()` | `list<SiteBlockPlacement>`: `Footer`, `Popup` (enum in `App\Domain\SiteBlocks`) |
| `settingsSchema()` | The Filament fields of the block's settings, possibly none. Their values come back to `props()` keyed by field name. Module blocks: [flat fields only](#flat-settings) |
| `component()` | The Vue component, relative to the owner's `resources/js` and under `components/`, a `.vue` file (`components/SubscribeBlock.vue`). Core blocks live in `resources/js/components/siteBlocks/` |
| `isAvailable()` | Whether the block can be offered in the panel and drawn now (a provider configured and answering). Asked on every page that shows it: keep it cheap |
| `props($settings, $user)` | The component's props for this person (`null` for a guest), only what they may see: a string-keyed array that survives `json_encode()` |

The registry (`SiteBlockRegistryInterface`: `all()`, `forPlacement()`, `find()`) only lists valid blocks; whether one is available now is the renderer's business.

## Flat settings

The footer and the popups keep the settings of their blocks inside their own stored JSON. An image or a rich text there would belong to them, not to the module, so no module could register those files in the media library or those editor images for the move to the media storage. So, in this first version, a module block only uses fields that keep one plain value:

- `TextInput` (short text, numbers with `->numeric()`, links with `->url()`…);
- `Select`, `ToggleButtons` and `Radio` with a single choice;
- `Toggle` and `Checkbox`.

Each field is named without dots (`button_label`, not `button.label`). No uploads, rich editors, text areas, repeaters, hidden fields or layout components (`Section`, `Grid`…). The registry checks it (`FlatSettingsSchemaRule`, an allowlist of exact classes: a subclass of `TextInput` is refused too) the first time the block is asked for and leaves out, with a warning, a module block with any other field or whose `settingsSchema()` throws. On top of that, a module block's `props()` only gets the scalar values (string, int, float, bool or `null`) of its own fields: a stored key that no field of the block has (one forged into the JSON, such as `innerHTML`) is dropped. Still, never pass `$settings` on to the component wholesale: return the props it needs.

The core cannot tell what a module keeps in an allowed field (an image URL in a `TextInput`, for instance): keeping files and editor HTML out of block settings is part of the contract.

Core blocks may use any field (the footer's text and logo blocks bring their own media and editor text sources).

## Drawing the blocks of a placement

For whoever owns a placement (the footer in the core, the popups in announcements):

1. **Offering.** Build the panel form's block definitions from `SiteBlockRegistryInterface::forPlacement($placement)` (every valid block, available or not): each block's `key()` and `label()`, with its `settingsSchema()`. Use `SiteBlockRendererInterface::available($placement)` only to decide which blocks can be **added**. A form built from `available()` alone would not know a stored block whose provider is down while someone edits, and saving would drop it.
2. **Storing.** Keep a list of `['key' => '<key>', 'settings' => [...]]` (plus anything of your own, such as a title).
3. **Resolving.** For each request, `resolve($blocks, $placement, $user)` returns `list<ResolvedSiteBlockDTO>` in order, without what cannot be drawn; `resolveBlock($key, $settings, $placement, $user)` resolves one (`null` when it cannot be drawn), handy when each stored entry carries more than the block. Send `$dto->toArray()` (`key`, `module`, `component`, `props`; empty props go out as `{}`) to the page.
4. **Drawing.** `SiteBlock.vue` from the public frontend API draws one resolved block:

```vue
<script setup lang="ts">
import SiteBlock from '@/components/layout/SiteBlock.vue';
import type { ResolvedSiteBlock } from '@/types/siteBlocks';

defineProps<{ blocks: ResolvedSiteBlock[] }>();
</script>

<template>
    <SiteBlock v-for="block in blocks" :key="block.key" :block="block" placement="popup" />
</template>
```

`placement` (`'footer' | 'popup'`, type `SiteBlockPlacement` in `@/types/siteBlocks`; `footer` when omitted) says where the block is drawn. The owner of a placement always passes its own: the footer passes `footer`, and the announcements popup will pass `popup`.

### Knowing where the block is drawn (core#718)

A block that looks different depending on where it is (the footer is dark in both colour schemes; a popup follows the page's) asks for its placement and never inspects the markup around it (the footer's `data-footer-column`, for instance, is the core's business and may change):

```vue
<script setup lang="ts">
import { computed } from 'vue';
import { useSiteBlockPlacement } from '@/composables/useSiteBlockPlacement';

const placement = useSiteBlockPlacement();
const onDark = computed(() => placement.value === 'footer');
</script>
```

- It is a `provide`/`inject` context (`useSiteBlockPlacement()`, public entry `@/composables/useSiteBlockPlacement`), not a prop: the block's props are exactly what `props()` returned, so a block that does not ask for the placement receives and renders what it always did, and nothing reaches its markup as an attribute.
- The value is reactive (`ComputedRef`). Outside `SiteBlock.vue` (a spec mounting the component alone) it is `footer`, unless the spec provides `SITE_BLOCK_PLACEMENT` (also exported) with its own `computed`.
- A module that imports `@/composables/useSiteBlockPlacement` needs a core with this entry (core ≥ the release with #718): on an older core the import map has no such entry and the module's chunk does not load, so it raises `requires.core` accordingly. A block that only reads `data-footer-column` keeps working, but should move to the placement.
- It is the placement the block was **drawn** in, not a setting: the PHP side already decides which blocks a placement offers (`placements()`).

## Failing closed

A block never breaks the page around it:

| Situation | What happens |
|---|---|
| The class cannot be built, is not a `SiteBlockInterface`, or its key, component path or placements are invalid | Left out of the registry, warning |
| The same class registered by a second owner | The second registration is ignored, warning |
| The key does not start with the module's own name (`core.` for the core), or a module called `core` | Left out, warning |
| Two blocks with the same key | Both left out, warning |
| A module block with settings that are not flat | Left out, warning |
| A stored key nobody registers (its module disabled or uninstalled) | Not drawn, warning |
| The block is not offered for that placement | Not drawn, warning |
| `isAvailable()` is false | Not drawn, no warning |
| `props()` throws `NothingToDrawException` (no logos, no events and no link…) | Not drawn, no warning: the block's title is not left alone either |
| `isAvailable()` or `props()` throws, or props are not keyed by name or cannot be encoded as JSON | Not drawn, warning |
| The Vue component fails to load or render | `SiteBlock.vue` drops it (`onErrorCaptured`, like `ModuleSlot.vue`) |

Every request builds the registry and the renderer again, so warnings are kept apart for a day (in the cache): the registry's per class and reason, the renderer's per key and reason. A footer shown on every page does not flood the log. Warnings carry the class, the module, the key and the reason; settings, props and exception messages are never logged, and an exception is named only by its class, since a module's message may carry what a person can see (an email in a provider's error).

## Testing

- Module tests: register the block and resolve it as the core will.

```php
$this->app->make(SiteBlockRegistryInterface::class)->register(SubscribeSiteBlock::class, 'newsletter');

$block = $this->app->make(SiteBlockRendererInterface::class)
    ->resolveBlock('newsletter.subscribe', ['heading' => 'Suscríbete'], SiteBlockPlacement::Footer, null);

$this->assertSame('components/SubscribeBlock.vue', $block?->component);
```

- Check that a block with your schema passes the rule: `$this->assertTrue((new FlatSettingsSchemaRule)->allows($block->settingsSchema()))`.
- Vue specs mount the module's component with the props `props()` returns; `SiteBlock.vue` itself is covered by the core.

## The footer

The core owns the footer placement (core ≥ the release with #697). *Sitio web → Pie de página* (`footer.manage`) composes up to four columns of blocks, each with an optional title, stored in the settings row `footer_layout` as `{"version":1,"columns":[{"blocks":[{"key","title","settings"}]}]}`. Without it, or without any block, the site draws its fixed footer unchanged.

- The page offers every valid footer block (`forPlacement(Footer)`); one that is not available now keeps its form and settings but cannot be added. A stored block nobody registers (its module disabled) is kept as it is when saving and is drawn again once its module comes back.
- Each request resolves the blocks for the person viewing (`FooterRendererInterface`, shared prop `footer`); a column left without blocks is dropped. A fixed bottom bar shows the copyright and the published legal pages.
- Core blocks: `core.links` (one of the four footer menus, `MenuLocation::footers()`, named on the same page), `core.text` (rich text, images in `footer/content/`, editor text source `footer`), `core.brand`, `core.contact`, and, since #698, `core.upcoming_events`, `core.button` and `core.logos` (below).
- A `core.links` block whose menu has no link the person can see (empty, all inactive, or only for another audience) throws `NothingToDrawException`, so it is not drawn and its title goes with it; the panel warns beside the menu selector when the chosen menu has no link a visitor without an account would see (resolved by `MenuService`, as the site does; asked once per menu and request).
- Columns: one on phones, two from `sm`, the configured number from `lg`.

### Upcoming events, button and logos (core#698)

- **`core.upcoming_events`**: the next `limit` published events (1–5, 3 by default; anything else is clamped or falls back to the default) that have not ended yet, like the rest of the site: `EventQueryServiceInterface::getNextEvents()` (`end_date >= now()`, soonest start first), so an event already under way, a multi-day one included, still shows. One query per block, tags not loaded, so no N+1. `startDate` goes through `TimezoneServiceInterface` and travels as an ISO instant with the display offset. The link goes to the event page and, optionally (`show_all_link`), to `/eventos`. Events have no audience of their own, so «what the person may see» is what is published; the block is the same for everybody. A module that gives events an audience must filter in `getNextEvents()`. With no events and no «all events» link there is nothing to draw (see below).
- **`core.button`**: `label`, `url` and the stored `style` (`primary` or `secondary`, anything else is `primary`), sent to the component as `variant` (a prop called `style` would be read by Vue as inline style). The link goes through `App\Domain\SiteBlocks\LinkTargetRule`, the one rule for links of site blocks: an absolute `https` address or a path starting with a single `/`. It refuses other schemes (`javascript:`, `data:`, `mailto:`…), `//host`, any backslash, whitespace and control characters, addresses with a user name and over 2048 characters; `http` only when the caller allows it (`app()->environment(['local', 'testing'])`). The panel validates with it (`CoreFooterBlock::linkField()`) and `props()` checks again, because the row can be written outside the panel: a button with no text or a link that is not allowed throws, so the renderer does not draw it.
- **`core.logos`**: `logos`, a list of `{image, alt, url}` (up to 8; `App\Domain\Footer\FooterLogos` reads it tolerantly). The image is uploaded to `footer/logos/` (`images` disk) or chosen from the media library (`MediaLibraryPicker`, core#480: the footer source releases through the core). `alt` is required in the form and a logo without it is left out when drawn; an unusable link is dropped but the logo stays. A logo of the library comes from Cloudinary scaled to twice the drawn height (`h_80,c_limit,q_auto,f_auto`; an SVG as uploaded), and the image carries `width`/`height` (the size it is drawn at, from the library's index, at most 40 px high) so the page does not jump; a logo the index does not know is served as stored without a size. Logos draw on a light chip because the footer is dark in both colour schemes. Without any usable logo there is nothing to draw (see below).

### Media of the footer

`FooterMediaSource` (type `footer`, record `layout`, `app/Filament/Media/Sources/`) claims `footer/` with its trailing slash, so it covers `footer/content/` (editor images of `core.text`, which used to show as «Origen desconocido» and could never be deleted) and `footer/logos/`. Its uses are the logos of every `core.logos` block (stored paths) and every delivery URL inside the `content` of a `core.text` block, wherever the file lives: `RichTextMediaSource` does not walk `footer_layout`, so a file of another folder that is only linked from the footer text would look unused. It reads the settings row from the table (`StoredFooterLayout`, never the settings cache) and an unreadable row throws, so no file looks unused because of it. `FooterSettings::save()` refreshes the record in the library index and releases the logos that left the footer (`MediaFileReleaserInterface`: deleted only if nobody else uses them, the footer itself read again); a failure there is logged and never fails the save, the nightly `media:sync` settles it.

Buttons in the footer all measure the same (core#734): a button drawn by a module block uses `BaseButton` with `size="sm"` (`px-4 py-2 text-sm`, at least 44 px high), which is what `core.button` draws too (the size classes live in `resources/js/utils/buttonClasses.ts`, internal to the core). Colours stay with each block: the footer is dark in both colour schemes.

Links of the core's blocks (`core.links`, `core.button`, `core.logos`) are drawn with Inertia only when they are pages (`resources/js/utils/inertiaLinks.ts`, `isInertiaPage()`): an absolute address, the panel (`/admin…`), `/up`, framework endpoints and anything whose last segment has a file extension are plain `<a>` links, since a client-side visit would load a JSON response or a download into the page.
