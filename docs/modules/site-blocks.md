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

Each field is named without dots (`button_label`, not `button.label`). No uploads, rich editors, text areas, repeaters, hidden fields or layout components (`Section`, `Grid`…). The registry checks it (`FlatSettingsSchemaRule`, an allowlist) the first time the block is asked for and leaves out, with a warning, a module block with any other field or whose `settingsSchema()` throws. On top of that, a module block's `props()` only gets the scalar values of its stored settings (string, int, float, bool or `null`).

Core blocks may use any field (the footer's text and logo blocks bring their own media and editor text sources).

## Drawing the blocks of a placement

For whoever owns a placement (the footer in the core, the popups in announcements):

1. **Offering.** Build the panel form from `SiteBlockRendererInterface::available($placement)` (valid and available now): each block's `key()` and `label()`, with its `settingsSchema()`.
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
    <SiteBlock v-for="block in blocks" :key="block.key" :block="block" />
</template>
```

## Failing closed

A block never breaks the page around it:

| Situation | What happens |
|---|---|
| The class cannot be built, is not a `SiteBlockInterface`, or its key, component path or placements are invalid | Left out of the registry, warning once per process |
| The key does not start with the module's own name (`core.` for the core), or a module called `core` | Left out, warning |
| Two blocks with the same key | Both left out, warning |
| A module block with settings that are not flat | Left out, warning |
| A stored key nobody registers (its module disabled or uninstalled) | Not drawn, warning |
| The block is not offered for that placement | Not drawn, warning |
| `isAvailable()` is false | Not drawn, no warning |
| `isAvailable()` or `props()` throws, or props are not keyed by name or cannot be encoded as JSON | Not drawn, warning |
| The Vue component fails to load or render | `SiteBlock.vue` drops it (`onErrorCaptured`, like `ModuleSlot.vue`) |

The renderer's warnings are kept apart for a day per key and reason (in the cache), so a footer shown on every page does not flood the log. Settings and props are never logged.

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
