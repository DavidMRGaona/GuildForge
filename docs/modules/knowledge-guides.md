# Writing knowledge guides

The knowledge base is the in-app help of GuildForge: guides for signed-in users of the site (audience `site`; the help centre is not open to guests) and for the people who use the admin panel (audience `panel`). The core ships its own guides; every module can ship the guides for what it adds. This page explains how a module writes them. For the rest of the module SDK, see the [module development guide](./README.md).

## Table of contents

1. [Where guides live](#where-guides-live)
2. [Front matter](#front-matter)
3. [Conditions](#conditions)
4. [Capabilities](#capabilities)
5. [Body syntax](#body-syntax)
6. [Categories](#categories)
7. [Related guides](#related-guides)
8. [Stable ids and renames](#stable-ids-and-renames)
9. [Contextual help](#contextual-help)
10. [Validation](#validation)
11. [Writing style](#writing-style)

---

## Where guides live

Guides are Markdown files with a front matter block, one file per guide and locale:

```
resources/knowledge/<locale>/<audience>/<slug>.md     # core: src/resources/knowledge/
modules/my-module/resources/knowledge/<locale>/...    # module
```

- `<locale>` is `es`, `en`, `pt_BR`... (two letters, optionally with a region). Any other directory name is a lint error.
- Below the locale, folders are free: they only organize files. The audience comes from the front matter, not from the path.
- The default and fallback locale is `es` (`config('knowledge.fallback_locale')`): a guide without a translation for the requested locale is shown in Spanish.
- The core scans its own folder and the `resources/knowledge` folder of every enabled module. The catalog is synchronized at deploy time with `php artisan knowledge:sync`; disabling a module hides its guides.

## Front matter

```markdown
---
id: my-module.sign-up
previous_ids: [my-module.join]
title: Apuntarte a una partida
summary: Reserva tu plaza en una partida en pocos pasos.
category: events-and-games
audience: site
order: 20
keywords: [apuntarse, inscribirse, reservar plaza, entrar en una mesa]
related: [my-module.cancel-sign-up]
featured: false
conditions:
  capabilities: [my-module.can_sign_up]
  permissions:
    all: [my-module:tables.create]
---
Body in Markdown.
```

| Field | Required | Notes |
|-------|----------|-------|
| `id` | yes | `<prefix>.<name>` with lowercase letters, digits and hyphens, at least one dot. The prefix MUST be the module name (`core` for the core). |
| `previous_ids` | no | Former ids of this guide, so old links keep working. See [Stable ids](#stable-ids-and-renames). |
| `title` | yes | Shown in lists and search. |
| `summary` | yes | One sentence, shown in lists and search results. |
| `category` | yes | A core category or one the module defines. Its audience must match the guide's. |
| `audience` | yes | `site` or `panel`. |
| `order` | no | Integer, default `100`. Lower first. |
| `keywords` | no | List of strings. Search indexes them. |
| `related` | no | List of guide ids. |
| `featured` | no | `true` or `false` (default). |
| `conditions` | no | Who sees the guide. See below. |

A guide with a missing required field, an unknown condition key or invalid YAML fails the lint.

Markers such as `{{site_name}}` are not resolved in `title` or `summary`; they only work in the body.

## Conditions

`conditions` decides who sees a guide. Every condition present must pass (they are combined with AND). A guide without conditions is shown to everybody allowed by its audience.

```yaml
conditions:
  capabilities: [my-module.can_sign_up, '!my-module.is_blocked']
  permissions:
    all: [my-module:tables.create]
    any: [my-module:tables.update, my-module:tables.delete]
  association_member: true
  modules: [my-module]
```

- `capabilities`: keys of registered [capabilities](#capabilities). Prefix with `!` to require that the capability does NOT pass (quote the value in YAML: `'!my-module.is_blocked'`).
- `permissions`: a map with `all` (the user needs every permission) and/or `any` (at least one). Use the keys exactly as registered: core keys look like `settings.manage`, module keys like `<module>:<name>`. The lint fails if a permission is not registered.
- `association_member`: `true` for members of the association only, `false` for people who are not. Membership is decided by the core (`AssociationMembershipServiceInterface`), never by a role name.
- `modules`: module names that must be enabled. A module's guides may only name the module itself: modules do not depend on each other, and the lint fails on any other name.

An unreachable or failing capability never grants access: it counts as not passing.

## Capabilities

A capability is a named yes/no question about a user, such as "can this person create tables?". Guides and action buttons use it to show only what the person can actually do.

Write one as a class that REUSES the domain rule that already decides the action, so the guide never disagrees with the application:

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Infrastructure\Knowledge;

use App\Application\Knowledge\Contracts\KnowledgeCapabilityInterface;
use Modules\MyModule\Application\Services\SignUpPolicyInterface;

final readonly class CanSignUpCapability implements KnowledgeCapabilityInterface
{
    public function __construct(private SignUpPolicyInterface $policy) {}

    public function key(): string
    {
        return 'my-module.can_sign_up';
    }

    public function passes(object $user): bool
    {
        return $this->policy->canSignUp($user);
    }
}
```

Register it from the module's service provider:

```php
/** @return array<class-string<KnowledgeCapabilityInterface>> */
public function registerKnowledgeCapabilities(): array
{
    return [CanSignUpCapability::class];
}
```

Rules:

- Keys are `<module>.<name>` (core capabilities use `core.`), for example `core.email_verification_required`.
- A module's guides and action buttons may use core capabilities and the module's own, never another module's (the lint fails).
- The class is resolved from the container, so constructor dependencies are injected.
- Do not reimplement permissions or membership: ask `AuthorizationServiceInterface`, `AssociationMembershipServiceInterface` or your own domain service.
- When a module is disabled, its capabilities are unregistered. A guide that depends on them should also list the module in `conditions.modules`.

## Body syntax

The body is CommonMark plus three extensions.

**Callouts** are block quotes with a marker on the first line: `> [!TIP]`, `> [!IMPORTANT]` or `> [!WARNING]`.

```markdown
> [!IMPORTANT]
> The link expires in one hour.
```

**Action buttons** link to a page of the application by route name. The route must exist and have no required parameters. Query parameters go after `?` as `key=value` pairs joined by `&` (letters, digits, dots, hyphens and underscores), for example to open a profile tab. The optional `if=` condition shows the button only when a permission or a capability passes (`!` negates it):

```markdown
[[action:profile.show|Ir a mi perfil]]
[[action:profile.show?tab=surveys|Ver mis encuestas]]
[[action:filament.admin.pages.site-settings|Abrir la configuración|if=settings.manage]]
[[action:my-module.tables.index|Ver partidas|if=!my-module.is_blocked]]
```

**Markers** are replaced with the current value when the guide is shown:

| Marker | Value |
|--------|-------|
| `{{site_name}}` | The name of the site (`guild_name` setting). |
| `{{association_role}}` | The display name of the association member role. |

Any other marker is a lint error. Markers do not work in `title`, `summary` or front matter.

Also:

- Raw HTML is not allowed (the lint fails). CommonMark autolinks such as `<https://example.org>` are fine.
- No screenshots: the interface changes and a stale image is worse than none. Describe the real labels instead.
- Fenced and inline code are literal: markers and angle brackets inside them are not checked.

## Categories

Every guide belongs to a category, shown as a group in the help centre.

- Core categories (site): `getting-started`, `account`, `events-and-games`, `campaigns`, `tournaments`, `membership`, `venue`, `community`.
- Core categories (panel): `panel-basics`, `content`, `users-and-roles`, `site-settings`, `modules`, `activities`.

### Choosing a category

Categories group guides by area, by what the person wants to do, not by module: people do not know which module runs a page. Guides of several modules share a category, next to the core's ones (`events-and-games` holds the core's events and the game tables and event registrations guides).

| Module | Site | Panel |
|--------|------|-------|
| game-tables | `events-and-games` (tables), `campaigns` (campaigns and invitations) | `activities` |
| event-registrations | `events-and-games` | `activities` |
| tournaments | `tournaments` | `activities` |
| venue-bookings | `venue` | `activities` |
| memberships | `membership` | its own category (fees fit no core one) |
| surveys | `community` | `content` |
| announcements | `community` | `content` |
| cookie-consent | `account` (cookie preferences) | `site-settings` |
| channel-notifications | `account`, only if people subscribe to something | `site-settings` |

- `activities` is the panel category for running the guild's activities: tables, campaigns, registrations, tournaments and bookings.
- `content` is for what editors publish; `site-settings` for modules that are only configured.
- A guide about a module's settings page goes in the same category as the guides on managing it: people look for the reminder settings next to the tables, not under `modules`, which is for installing, enabling and updating modules.
- A new module follows the same rule. It defines its own category only when no core one fits, and never one per module just to group its guides.

A guide's `audience` must match its category's audience. When none fits, the module defines its own in `resources/knowledge/categories.php`:

```php
<?php

declare(strict_types=1);

return [
    [
        'id' => 'my-module',
        'audience' => 'panel',
        'order' => 200,
        'icon' => 'heroicon-o-puzzle-piece',
        'label_key' => 'my-module::knowledge.categories.my-module',
    ],
];
```

All five keys are required. `label_key` is a translation key for the category name; add it to the module's translations. Do not reuse a core category id.

## Related guides

`related` lists guides to suggest at the end. A module can relate its guides to the core's and to its own, never to another module's: modules do not depend on each other. The lint fails when a related id does not exist in the core or in the module, appears twice or points to the guide itself.

## Stable ids and renames

The id of a guide is its identity: links, "new" and "updated" badges and read state hang from it. Never change an id just because the title changed.

When you must rename or merge a guide, give it the new `id` and list the old one in `previous_ids`; old links then redirect. The lint fails if a previous id is the id of an existing guide, is repeated, or is claimed by two guides.

## Contextual help

Link a page to its guide so people find help where they need it. The link only appears when the person may see the guide.

**Public pages (Vue)**: use `HelpLink` from the frontend public API.

```vue
<script setup lang="ts">
import HelpLink from '@/components/knowledge/HelpLink.vue';
</script>

<template>
    <h1>{{ $t('tables.title') }} <HelpLink guide="my-module.sign-up" /></h1>
</template>
```

`label` overrides the accessible label (default: the translated "Help"). `HelpLink` and `useKnowledgeVisibility` are public entries (`resources/js/public-api.json`), so module builds can import them.

**Admin panel (Filament)**: add the `App\Filament\Concerns\HasKnowledgeGuideAction` trait to a page and add the action to its header actions. The action is named `contextualHelp`, opens the guide in the help page and returns `null` (no button) when the guide does not exist or the user cannot see it:

```php
use App\Filament\Concerns\HasKnowledgeGuideAction;

class ListTables extends ListRecords
{
    use HasKnowledgeGuideAction;

    protected function getHeaderActions(): array
    {
        return array_filter([
            $this->knowledgeGuideAction('my-module.manage-tables'),
            CreateAction::make(),
        ]);
    }
}
```

## Validation

```bash
php artisan knowledge:lint --module=my-module
```

With the Docker development stack, `make knowledge-lint MODULE=my-module` runs the same check from the core's root.

The command checks the core's guides and the module's: front matter, ids and prefixes, categories and audiences, `related`, `previous_ids`, registered permissions and capabilities, raw HTML, unknown markers, and action buttons (route exists, no required parameters, valid `if=`). It exits with a failure and lists each issue as `file: message`.

Run it in the module's tests so CI blocks a broken guide:

```php
use Tests\Support\Modules\ModuleTestCase;

final class KnowledgeGuidesTest extends ModuleTestCase
{
    public function test_guides_are_valid(): void
    {
        $this->assertModuleKnowledgeGuidesAreValid('my-module');
    }
}
```

The helper enables the module if needed, so its capabilities and permissions are registered when the lint runs.

## Writing style

- Write in the second person («Pulsa **Guardar**»), in the language of the folder, and in Spanish-style capitalization: only the first word of a title or label is capitalized.
- Explain one task per guide. Use short numbered steps, one action each.
- Use the real labels of the interface, in bold, exactly as people see them.
- Say what happens after the last step, and add a short «Si algo no sale bien» section for common problems.
- Fill `keywords` with the words people would type, including everyday phrasing and common misspellings of the key terms. Do not repeat the title.
- Never write the name of the association role: use `{{association_role}}`, because each site names it differently. The same goes for the site name: `{{site_name}}`.
- Do not describe things that depend on the user's permissions without a condition. Use `conditions` or `if=` so nobody reads steps they cannot follow.
- Use callouts sparingly: `TIP` for shortcuts, `IMPORTANT` for something people must know, `WARNING` for data loss or irreversible effects.
