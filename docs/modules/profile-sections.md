# Profile sections

Every account has a public profile at `/miembros/<handle>`. The core draws its header (public name, avatar, banner, «Socio» badge, member since) and its own «Sobre mí» section; the modules add the rest: a list of the person's latest forum posts, the tables they run, how many messages they wrote. A module offers **sections** (a list, a summary or a text, drawn by a Vue component) and **stats** (a number with an optional link); the owner chooses, in the profile editor, which ones show and in what order. No module depends on another, and none of them decides who may see the profile: the core does, before anything is drawn.

Requires core ≥ the release with #742; set `requires.core` to it.

## Contents

1. [Offering a section](#offering-a-section)
2. [The section contract](#the-section-contract)
3. [Stats](#stats)
4. [Checklist for every section and stat](#checklist-for-every-section-and-stat)
5. [The owner's layout](#the-owners-layout)
6. [Drawing a profile](#drawing-a-profile)
7. [Failing closed](#failing-closed)
8. [The core's section](#the-cores-section)
9. [Testing](#testing)

## Offering a section

Implement `App\Application\Profiles\Contracts\ProfileSectionInterface` and return the class from the provider's `registerProfileSections()`. Sections are built from the container, so they may take dependencies in their constructor.

```php
use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\SiteBlocks\Exceptions\NothingToDrawException;
use App\Domain\Profiles\ProfileRegion;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class LatestPostsProfileSection implements ProfileSectionInterface
{
    public function __construct(
        private ForumBrowser $browser,
    ) {}

    public function key(): string
    {
        return 'forum.latest_posts';
    }

    public function label(): string
    {
        return __('forum::messages.profile.latest_posts');
    }

    public function description(): string
    {
        return __('forum::messages.profile.latest_posts_description');
    }

    public function component(): string
    {
        return 'components/LatestPostsSection.vue';
    }

    public function region(): ProfileRegion
    {
        return ProfileRegion::Main;
    }

    public function order(): int
    {
        return 10;
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function props(ProfileOwnerDTO $owner, ?Authenticatable $viewer): array
    {
        // Only what this viewer may read in the forum itself: the same rule as the forum pages
        $posts = $this->browser->latestPostsBy($owner->id, $viewer, limit: 5);

        if ($posts === []) {
            throw NothingToDrawException::make();
        }

        return ['posts' => array_map(static fn (PostSummary $post): array => [
            'title' => $post->discussionTitle,
            'url' => $post->url,
            'excerpt' => $post->excerpt,
        ], $posts)];
    }
}
```

```php
// ForumServiceProvider
public function registerProfileSections(): array
{
    return [LatestPostsProfileSection::class];
}
```

The component lives in the module's `resources/js/components/LatestPostsSection.vue` and receives the props as its own. It draws only the content: the card and the title (`label()`) are drawn by the core, so every section looks the same whichever module draws it. It is loaded like a module slot component (the module's Vite build in production), so build it with the module.

## The section contract

| Method | What it returns |
|---|---|
| `key()` | `<module>.<section>` (the module's own name only) or `core.<section>` for the core. The rules of site block keys: the owner kebab-case, the name snake_case or kebab-case, 100 characters at most. Stored in the owners' layouts: never change it once a section is offered |
| `label()` | The section's title on the profile and its name in the profile editor, translated |
| `description()` | One line for the profile editor saying what the section shows, translated |
| `component()` | The Vue component, relative to the owner's `resources/js` and under `components/`, a `.vue` file (`components/LatestPostsSection.vue`). Core sections live in `resources/js/components/profileSections/` |
| `region()` | `ProfileRegion::Main` (the wide column) or `ProfileRegion::Aside` (the narrow one), enum in `App\Domain\Profiles`. On phones there is one column |
| `order()` | The default position within its region, lowest first; the owner may reorder |
| `enabledByDefault()` | Whether the section shows on a profile whose owner never turned it on or off |
| `isAvailable()` | Whether the section can be offered and drawn now. Asked on every profile: keep it cheap |
| `props($owner, $viewer)` | The component's props about this owner, for this person (`null` for a guest): only what they may see, a string-keyed array that survives `json_encode()`. Throw `NothingToDrawException` when there is nothing to show |

`ProfileOwnerDTO` carries only the owner's account `id` (to query the module's own data), `handle` and `publicName` (`null` only when the owner looks at their own profile before choosing one). Never the email, the account name or the roles: a module that needs to know something else about the owner asks its own services, for the viewer.

The registry (`ProfileSectionRegistryInterface`: `all()`, `find()`, `moduleOf()`; a singleton) only lists valid sections, by default order; drawing them is the renderer's business.

## Stats

A stat is a number on the profile's stats row (messages written, tables run…), apart from the sections, so a counter does not vanish because its list is turned off, nor the other way round. Implement `App\Application\Profiles\Contracts\ProfileStatInterface` and return it from `registerProfileStats()`:

```php
use App\Application\Profiles\Contracts\ProfileStatInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Profiles\DTOs\ProfileStatDTO;
use Illuminate\Contracts\Auth\Authenticatable;

final readonly class MessagesProfileStat implements ProfileStatInterface
{
    public function __construct(
        private ForumBrowser $browser,
    ) {}

    public function key(): string
    {
        return 'forum.messages';
    }

    public function label(): string
    {
        return __('forum::messages.profile.messages');
    }

    public function order(): int
    {
        return 10;
    }

    public function enabledByDefault(): bool
    {
        return true;
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function stat(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ?ProfileStatDTO
    {
        // Counted in the categories this viewer may read, never in the hidden ones
        $count = $this->browser->countPostsBy($owner->id, $viewer);

        return $count === null ? null : new ProfileStatDTO($count, '/foro/buscar?autor='.$owner->handle);
    }
}
```

| Method | What it returns |
|---|---|
| `key()` | Same rules as a section key; stats and sections are stored apart in the layout, but a module should still not reuse a section key for a stat |
| `label()` | The name beside the number and in the profile editor, translated |
| `order()` | The default position on the stats row, lowest first; the owner may reorder |
| `enabledByDefault()` | Whether the stat shows when the owner never turned it on or off |
| `isAvailable()` | Whether the stat can be offered and counted now; keep it cheap |
| `stat($owner, $viewer)` | `ProfileStatDTO(int $value, ?string $url)`: a whole number of zero or more, counted for this person, and optionally where it links (an absolute `https` address or a path starting with a single `/`, the rule of site block links, `LinkTargetRule`). `null` when there is nothing to show |

Return `null`, never `0`, when the viewer cannot see the things counted: a 0 would say «this person wrote nothing» when they wrote in a category the viewer cannot read. A real 0 the viewer could check is fine.

The page shows every stat in the owner's order; a summary card (the forum's user card) shows the first four (`ResolvedProfileDTO::CARD_STATS`).

## Checklist for every section and stat

Review a section or stat against this list before offering it:

- **Decided for the person viewing.** `props()` and `stat()` answer for `$viewer`, with the **same access service as the page the data comes from** (the forum's readable categories, a table's visibility rules, a sign-up being public…), never a looser rule written for the profile. A guest (`null`) sees what a guest sees on those pages.
- **Nothing private about the owner.** Only what the owner already shows to that viewer elsewhere on the site: never their email, account name, roles, sign-ups that are not public, hidden or moderated content, drafts, or anything only moderators see. Content posted under a hidden author (an NPC, an anonymous post) never counts or lists for anyone who could not see who wrote it.
- **The core already decided the profile is visible.** A section never repeats or loosens that decision, and never leaks something because the profile is public: a public profile does not make the owner's private things public.
- **Nothing to show is quiet.** An empty list throws `NothingToDrawException` (the title goes with it); a count the viewer cannot see is `null`.
- **Plain data in props.** Ids, titles, links and counts the component needs, picked one by one: never models, settings or HTML. Text is drawn as text by the component; a component that must draw HTML sanitizes it on the server first.
- **Cheap.** A profile is drawn on every visit: one or two queries per section, limited (a handful of rows), no N+1, nothing slow in `isAvailable()`.

## The owner's layout

The owner's choices are stored with their profile (`user_profiles.layout`, `ProfileLayout` in `App\Domain\Profiles\ValueObjects`) as `{"sections":[{"key","enabled"}],"stats":[{"key","enabled"}]}`, each list in the owner's order.

- **Order**: first the keys the layout lists, in its order; then every other section or stat by its `order()` (then its key). So a section a module adds later appears after the ones the owner arranged, until they move it.
- **On or off**: the layout's choice, or `enabledByDefault()` when the layout does not mention the key.
- **Unknown keys** (a disabled or uninstalled module): ignored when drawing and kept in the layout, so turning the module on again brings the owner's choice back.
- Sections are ordered within their own region: the layout's order only decides among the sections of one column.

## Drawing a profile

For whoever draws a profile (the core's profile page, a summary card):

1. **Access first.** Decide whether the person may see the profile at all (the core's `ProfileAccessRule`); the renderer does not.
2. **Render.** `ProfileRendererInterface::render(ProfileOwnerDTO $owner, ?Authenticatable $viewer): ResolvedProfileDTO` (scoped to the request; it never throws). Send `$dto->toArray()` to the page:

```json
{
    "header": {
        "handle": "ana-garcia",
        "publicName": "Ana García",
        "avatarPublicId": "avatars/ana.jpg",
        "bannerPublicId": null,
        "memberSince": "2024-06-03T12:00:00+02:00",
        "showsMembership": true,
        "isOwner": false
    },
    "stats": [{ "key": "forum.messages", "label": "Mensajes", "value": 394, "url": "/foro/buscar?autor=ana-garcia" }],
    "sections": {
        "main": [{ "key": "forum.latest_posts", "module": "forum", "component": "components/LatestPostsSection.vue", "region": "main", "label": "Últimos mensajes", "props": { "posts": [] } }],
        "aside": [{ "key": "core.about", "module": null, "component": "components/profileSections/AboutSection.vue", "region": "aside", "label": "Sobre mí", "props": { "lines": [] } }]
    }
}
```

   - `header.memberSince` is when the account was created, an ISO instant with the display zone's offset (`TimezoneServiceInterface::iso()`); `showsMembership` is true only when the owner chose to show the badge and holds the association role (a failing check hides it); `isOwner` says whether the viewer is the owner. The account name and the email never travel.
   - `stats` and each region's `sections` are in the owner's order and hold only what can be drawn; empty props go out as `{}`.

3. **Draw.** Each section with `ProfileSection.vue` from the public frontend API; it draws the card and the title (an `<h2>` labelling the `<section>`) and the owner's component inside:

```vue
<script setup lang="ts">
import ProfileSection from '@/components/layout/ProfileSection.vue';
import type { ResolvedProfile } from '@/types/profiles';

defineProps<{ profile: ResolvedProfile }>();
</script>

<template>
    <div class="grid gap-6 lg:grid-cols-3">
        <div class="space-y-6 lg:col-span-2">
            <ProfileSection v-for="section in profile.sections.main" :key="section.key" :section="section" />
        </div>
        <div class="space-y-6">
            <ProfileSection v-for="section in profile.sections.aside" :key="section.key" :section="section" />
        </div>
    </div>
</template>
```

Types in `@/types/profiles`: `ResolvedProfile`, `ProfileHeader`, `ResolvedProfileStat`, `ResolvedProfileSection`, `ProfileRegion` (`'main' | 'aside'`) and `ProfileTextSegment` (a piece of the «Sobre mí» text).

## Failing closed

A section or stat never breaks the profile around it:

| Situation | What happens |
|---|---|
| The class cannot be built, is not a `ProfileSectionInterface` / `ProfileStatInterface`, its key is invalid, or (sections) its component path is invalid or `region()` throws | Left out of the registry, warning |
| The same class registered by a second owner | The second registration is ignored, warning |
| The key does not start with the module's own name (`core.` for the core), or a module called `core` | Left out, warning |
| Two sections (or two stats) with the same key | Both left out, warning |
| A key the owner turned on that nobody registers (its module disabled or uninstalled) | Not drawn, warning |
| Turned off by the owner, or off by default and never turned on | Not drawn, no warning |
| `isAvailable()` is false | Not drawn, no warning |
| `props()` throws `NothingToDrawException`, or `stat()` returns `null` | Not drawn, no warning: a section's title is not left alone either |
| `isAvailable()`, `props()` or `stat()` throws, props are not keyed by name or cannot be encoded as JSON, a negative count, or a link `LinkTargetRule` refuses | Not drawn, warning |
| The Vue component fails to load or render | `ProfileSection.vue` drops it, title included (`onErrorCaptured`, like `SiteBlock.vue`) |
| The profile row or the account cannot be read, or the membership check throws | The header is drawn without what failed (no badge), the layout takes the defaults, warning |

Every request builds the registries and the renderer again, so warnings are kept apart for a day in the cache (`App\Infrastructure\Support\DailyWarnings`, prefix `profiles:warned:`, the same mechanism as site blocks): the registries' per class and reason, the renderer's per key and reason. Warnings carry the class, the module, the key and the reason; props, counts and exception messages are never logged, and an exception is named only by its class.

## The core's section

`core.about` («Sobre mí», `App\Infrastructure\Profiles\Sections\AboutProfileSection`): the owner's bio, in the aside, on by default. It is plain text: the server cuts it into lines and pieces of text and links (`App\Domain\Profiles\ProfileBioText`), and `AboutSection.vue` draws every piece as text, so nothing the owner wrote is ever drawn as HTML. Line breaks are kept (a run of blank lines counts as one); only `http` and `https` addresses become links (never with a user name before the host, never over 2048 characters, the sentence's punctuation left out), drawn with `rel="nofollow ugc noopener"`. An empty bio draws nothing.

## Testing

- Module tests: register the section and render a profile as the core will.

```php
$this->app->make(ProfileSectionRegistryInterface::class)->register(LatestPostsProfileSection::class, 'forum');

$profile = $this->app->make(ProfileRendererInterface::class)
    ->render(new ProfileOwnerDTO($owner->id, 'ana-garcia', 'Ana García'), $viewer);

$this->assertSame(['forum.latest_posts'], array_map(fn ($section) => $section->key, $profile->main));
```

- Test the checklist, not only the happy path: a guest, a signed-in account and a moderator see what each sees on the module's own pages; a hidden category, an NPC author or a private sign-up never shows or counts; nothing to show throws `NothingToDrawException` (or returns `null` for a stat).
- Vue specs mount the module's component with the props `props()` returns; `ProfileSection.vue` itself is covered by the core.
