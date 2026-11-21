# Profile sections

Every account has a public profile at `/miembros/<handle>`. The core draws its header (public name, avatar, banner, «Socio» badge, member since) and its own «Sobre mí» section; the modules add the rest: a list of the person's latest forum posts, the tables they run, how many messages they wrote. A module offers **sections** (a list, a summary or a text, drawn by a Vue component), **stats** (a number with an optional link) and **featured badges** (up to three under the name, core ≥ the release with #776); the owner chooses, in the profile editor, which ones show and in what order. No module depends on another, and none of them decides who may see the profile: the core does, before anything is drawn.

Requires core ≥ the release with #742; set `requires.core` to it.

## Contents

1. [Offering a section](#offering-a-section)
2. [The section contract](#the-section-contract)
3. [Stats](#stats)
4. [Checklist for every section and stat](#checklist-for-every-section-and-stat)
5. [The owner's layout](#the-owners-layout)
6. [How the core draws a profile](#how-the-core-draws-a-profile)
7. [Profile card](#profile-card)
8. [Badges](#badges)
9. [Failing closed](#failing-closed)
10. [The core's section](#the-cores-section)
11. [Testing](#testing)

## Offering a section

Implement `App\Application\Profiles\Contracts\ProfileSectionInterface` and return the class from the provider's `registerProfileSections()`. Sections are built from the container, so they may take dependencies in their constructor.

The registry keeps only the class and what it read when checking it (key, owner, default order, on by default), never an instance. The core builds the section again from the container once in each request or job that draws profiles, and only when it is on (a section the owner turned off is never built), so a constructor dependency (a request-scoped service, a row read once) is always the current one. Never keep state across requests yourself: no static properties or caches inside the section.

```php
use App\Application\Profiles\Contracts\ProfileSectionInterface;
use App\Application\Profiles\DTOs\ProfileOwnerDTO;
use App\Application\Exceptions\NothingToDrawException;
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

`NothingToDrawException` is `App\Application\Exceptions\NothingToDrawException`, shared with site blocks; the older `App\Application\SiteBlocks\Exceptions\NothingToDrawException` is a subclass kept for the blocks that already throw it, so either name works, but new code throws the common one.

The registry (`ProfileSectionRegistryInterface`, a singleton: `all()` and `find()` return `ProfileRegistrationDTO` with `key`, `module`, `class` and `order`) only lists valid sections, by default order; drawing them is the core's business.

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

**Optional description** (core ≥ the release with #743): a stat may also implement `App\Application\Profiles\Contracts\DescribesProfileStat`, whose `description(): string` is one translated line the profile editor shows under its label («Mensajes que ha escrito en el foro»). It is a separate interface on purpose, so a stat written for the contract of 3.79 keeps working unchanged; without it the editor shows only the label. The interface only exists from that release on: a module that implements it must set `requires.core` to it, since on an older core the class would not load.

```php
use App\Application\Profiles\Contracts\DescribesProfileStat;
use App\Application\Profiles\Contracts\ProfileStatInterface;

final readonly class MessagesProfileStat implements ProfileStatInterface, DescribesProfileStat
{
    public function description(): string
    {
        return __('forum::messages.profile.messages_description');
    }

    // ...
}
```

The page shows every stat in the owner's order; a summary card (the forum's user card) shows the first four (`ResolvedProfileDTO::CARD_STATS`).

## Checklist for every section and stat

Review a section or stat against this list before offering it:

- **Decided for the person viewing.** `props()` and `stat()` answer for `$viewer`, with the **same access service as the page the data comes from** (the forum's readable categories, a table's visibility rules, a sign-up being public…), never a looser rule written for the profile. A guest (`null`) sees what a guest sees on those pages.
- **Nothing private about the owner.** Only what the owner already shows to that viewer elsewhere on the site: never their email, account name, roles, sign-ups that are not public, hidden or moderated content, drafts, or anything only moderators see. Content posted under a hidden author (an NPC, an anonymous post) never counts or lists for anyone who could not see who wrote it.
- **The core already decided the profile is visible.** A section never repeats or loosens that decision, and never leaks something because the profile is public: a public profile does not make the owner's private things public.
- **Nothing to show is quiet.** An empty list throws `NothingToDrawException` (the title goes with it); a count the viewer cannot see is `null`.
- **Plain data in props.** Ids, titles, links and counts the component needs, picked one by one: never models, settings or HTML. Text is drawn as text by the component; a component that must draw HTML sanitizes it on the server first.
- **Safe links.** Every link in props (and a stat's `url`) comes from the module's own routes (`route()`, a path it builds from ids) or goes through `App\Domain\SiteBlocks\LinkTargetRule::normalize()`. Never pass on an address a person typed (a website field, a link in a post) without validating it: the component binds it to `href`.
- **Cheap.** A profile is drawn on every visit: one or two queries per section, limited (a handful of rows), no N+1, nothing slow in `isAvailable()`.

## The owner's layout

The owner's choices are stored with their profile (`user_profiles.layout`, `ProfileLayout` in `App\Domain\Profiles\ValueObjects`) as `{"sections":[{"key","enabled"}],"stats":[{"key","enabled"}]}`, each list in the owner's order.

- **Order**: first the keys the layout lists, in its order; then every other section or stat by its `order()` (then its key). So a section a module adds later appears after the ones the owner arranged, until they move it.
- **On or off**: the layout's choice, or `enabledByDefault()` when the layout does not mention the key.
- **Unknown keys** (a disabled or uninstalled module): ignored when drawing and kept in the layout, so turning the module on again brings the owner's choice back.
- Sections are ordered within their own region: the layout's order only decides among the sections of one column.

### The profile editor

The owner edits their profile from the profile itself («Editar perfil», core ≥ the release with #743). The editor lists every section and every stat that is registered and available now (`isAvailable()`), in the owner's order, each with its `label()`, its description (a section's `description()`; a stat's only when it implements `DescribesProfileStat`), a switch and buttons to move it up or down. To list them the core builds each one from the container once per request (`ProfileLayoutOptionsInterface`) and reads only those methods, never `props()` or `stat()`; keep the constructor and `isAvailable()` cheap. One that cannot be built, has another key than it was registered with, or throws is not offered (warned once a day, like the renderer).

Saving (`PUT /miembros/{handle}`) accepts only the keys offered at that moment: anything else is refused with a field error. What the layout said of keys not offered now (a disabled module, an unavailable section) is kept after the new choice, so it comes back with its module. The editor's preview can only show what the page already drew: a section or stat turned on there appears once saved, except «Sobre mí», which the preview draws from the text being written.

## How the core draws a profile

**Modules never call `ProfileRendererInterface`.** Only the core draws a profile: its profile page (and the core's summary card), after `PublicProfileServiceInterface::findByHandle()` (core#741) has applied `ProfileAccessRule` to decide whether the person may see the profile at all. The renderer does not decide that, so a module calling it could draw a hidden profile. A module that wants to show a person's summary uses the [profile card](#profile-card) (`ProfileCardServiceInterface::card()` or `GET /miembros/{handle}/tarjeta`), never the renderer. This section is for reading what the page receives.

1. **Access first.** `PublicProfileServiceInterface::findByHandle($handle, $viewer)` applies the access rule (and the redirect of an old address); only a visible profile goes on.
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
        "isOwner": false,
        "badges": [{ "key": "achievements.first-game", "label": "Primera partida", "description": "Jugó su primera partida.", "icon": "dices", "colour": "amber", "imageUrl": null, "url": "/logros" }]
    },
    "stats": [{ "key": "forum.messages", "label": "Mensajes", "value": 394, "url": "/foro/buscar?autor=ana-garcia" }],
    "sections": {
        "main": [{ "key": "forum.latest_posts", "module": "forum", "component": "components/LatestPostsSection.vue", "region": "main", "label": "Últimos mensajes", "props": { "posts": [] } }],
        "aside": [{ "key": "core.about", "module": null, "component": "components/profileSections/AboutSection.vue", "region": "aside", "label": "Sobre mí", "props": { "lines": [] } }]
    }
}
```

   - `header.publicName` is the account's public name (`display_name`), read by the renderer, never taken from the caller; `header.memberSince` is when the account was created, an ISO instant with the display zone's offset (`TimezoneServiceInterface::iso()`); `showsMembership` is true only when the owner chose to show the badge and holds the association role (a failing check hides it); `isOwner` says whether the viewer is the owner; `badges` are the owner's featured [badges](#badges), drawn under the name. The account name and the email never travel.
   - `stats` and each region's `sections` are in the owner's order and hold only what can be drawn; empty props go out as `{}`.

3. **Draw.** Each section with `ProfileSection.vue` from the public frontend API; it draws the card and the title (an `<h2>` labelling the `<section>`) and the owner's component inside:

```vue
<script setup lang="ts">
import ProfileSection from '@/components/layout/ProfileSection.vue';
import type { ResolvedProfile } from '@/types/profileSections';

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

Types in `@/types/profileSections`: `ResolvedProfile`, `ProfileHeader`, `ResolvedProfileStat`, `ResolvedProfileSection`, `ProfileRegion` (`'main' | 'aside'`), `ProfileTextSegment` (a piece of the «Sobre mí» text) and `ProfileCard` (the [profile card](#profile-card)).

## Profile card

A summary of a public profile for other parts of the site to show in a card: the forum opens one when someone clicks an author's name. Requires core ≥ the release with #744. It shows what the profile page shows the same person, and no more: the same `ProfileAccessRule`, the header, and the first four stats (`ResolvedProfileDTO::CARD_STATS`) that can be drawn, in the owner's order (turned off, unavailable, `null` or failing stats are skipped, and the next one takes their place). No section is built for a card, and once it holds four stats no further stat is counted.

### From the browser: `GET /miembros/{handle}/tarjeta`

Route `profiles.card`. A visible profile answers `200` with this JSON (`ProfileCardDTO::toArray()`):

```json
{
    "handle": "ana-garcia",
    "publicName": "Ana García",
    "url": "https://example.org/miembros/ana-garcia",
    "avatarUrl": "https://res.cloudinary.com/demo/image/upload/w_128,h_128,c_fill,g_face,q_auto,f_auto/avatars/ana",
    "bannerUrl": null,
    "memberSince": "2024-06-03T12:00:00+02:00",
    "showsMembership": true,
    "isOwner": false,
    "stats": [{ "key": "forum.messages", "label": "Mensajes", "value": 394, "url": "/foro/buscar?autor=ana-garcia" }],
    "badges": [{ "key": "achievements.first-game", "label": "Primera partida", "description": "Jugó su primera partida.", "icon": "dices", "colour": "amber", "imageUrl": null, "url": "/logros" }]
}
```

| Field | Meaning |
|---|---|
| `handle` | The profile's current address. |
| `publicName` | The account's public name. `null` only on the owner's own card while they have none (nobody else gets a card then): draw the address or «sin nombre público», never anything else. |
| `url` | The profile page, absolute. |
| `avatarUrl` | `https` image, 128 × 128 cropped around the face (`ProfileCardDTO::AVATAR_SIZE`; an animated avatar stays animated), or `null`: draw the initials. |
| `bannerUrl` | `https` image, 600 × 200 (`BANNER_WIDTH` × `BANNER_HEIGHT`, the page's 3:1), or `null`. |
| `memberSince` | When the account was created, ISO 8601 with the display zone's offset (`TimezoneServiceInterface::iso()`); `null` if it could not be read. Show the month and year as written, without converting it again. |
| `showsMembership` | Draw the «Socio» badge: true only when the owner chose to show it and holds the association role. |
| `isOwner` | The person asking is the owner (hide «Ignorar», offer «Editar perfil»…). |
| `stats` | At most four `{key, label, value, url}`, in the owner's order; `url` is a path or an `https` address of the site (`LinkTargetRule`), or `null`. |
| `badges` | At most three featured [badges](#badges) (`ProfileBadge`), in the owner's order; draw them with `ProfileBadges.vue`. |

Nothing else travels: never the account id, the account name, the email, the roles or the bio.

- **404.** A profile the person may not see, an unknown, malformed or reserved address, a deactivated or anonymized account, an account without a profile, or a card that could not be made all answer the same 404, so the card never tells whether an account exists: for the same request, the body, `Content-Type` and `X-Robots-Tag: noindex, nofollow` are the profile page's own, and it is private (`Cache-Control: no-cache, private`, like the page's 404). The only difference with the page is the limit's `X-RateLimit-Limit` and `X-RateLimit-Remaining`, which every answer of the card carries and which say nothing about the address. Ask with `Accept: application/json` to get it as JSON.
- **An old address** (or the current one written differently) answers `200` with the card of the current address, not a redirect: `handle` and `url` already name it, the client needs one request, and a permanent redirect that depends on who asks is never left for a cache. It does so only once the access rule let the person see the profile, like the page's 301.
- **Not cached.** A card depends on who asks: the `200` carries `Cache-Control: no-store, private`. Ask again when the card opens; do not keep it across page loads.
- **Limit.** Its own limit, `profile-card`: 30 requests a minute per account when signed in, per network address otherwise; unknown addresses count alike. Over it answers `429` with `Retry-After` (and `X-Robots-Tag`, like every answer): say the card cannot be shown right now, never that the person does not exist. The limit runs inside the web group, so during an impersonation it counts for the account being impersonated and, like everything then, is never stored.
- **During an impersonation** it is decided for the account being impersonated, like the page.

```ts
import type { ProfileCard } from '@/types/profileSections';

const response = await fetch(`/miembros/${encodeURIComponent(handle)}/tarjeta`, {
    headers: { Accept: 'application/json' },
});
// 404 and 429: no card (never «no existe»)
const card = response.ok ? ((await response.json()) as ProfileCard) : null;
```

### From the server: `ProfileCardServiceInterface::card()`

A module that builds its own answer (a JSON with the card and its own data, a page with authors' cards) asks the core by account id:

```php
use App\Application\Profiles\DTOs\ProfileCardDTO;
use App\Application\Profiles\Services\ProfileCardServiceInterface;

$card = $this->cards->card($authorId, $request->user()); // ProfileCardServiceInterface $cards

return [
    'card' => $card?->toArray(), // null: show the name the module already shows, without a link
    'messages' => $count,
];
```

- `ProfileCardServiceInterface` (`App\Application\Profiles\Services`), an interface of its own so a module's test doubles of it keep working when the core's other profile services grow; its only method is `card(string $userId, ?Authenticatable $viewer): ?ProfileCardDTO`. The id is read without regard to case. The viewer is the person who will see the card (the request's user, or `null` for a guest), never the author: the access rule decides for them.
- `null` when the viewer may not see the profile, when the account has no profile (an id that is not a UUID, unknown, deactivated, anonymized, no profile row) or when anything fails (logged as a warning with the account id and the exception's class only). It never throws; an id that is not a UUID is not even looked up.
- `ProfileCardDTO` holds the fields of the JSON above as properties (`$card->stats` is a list of `ResolvedProfileStatDTO`, `$card->badges` of `ProfileBadgeDTO`); `toArray()` is that JSON.
- Ask it only for people the page actually shows as such (an author whose name the viewer sees), never for a hidden NPC author: `card()` decides who may see the profile, not whether showing that this account wrote something is allowed.
- Each call reads the profile and counts up to four stats: for a list, ask when a card opens (the endpoint) rather than for every author at once.
- It asks for the request's (or job's) profile rows and renderer on each call, so it can be injected anywhere, a singleton included.

## Badges

A profile shows up to three **featured badges** under the name, on its card and, through the batched call below, next to its owner wherever a module shows authors (the forum's post headers). Requires core ≥ the release with #776. The achievements module gives them (the badges each owner chose); any module may, through a **badge source**. No module knows another: the forum draws whatever badges the core gives it, without knowing where they come from.

### Giving badges: `ProfileBadgeSourceInterface`

Implement `App\Application\Profiles\Contracts\ProfileBadgeSourceInterface` and return it from `registerProfileBadgeSources()`:

```php
use App\Application\Profiles\Contracts\ProfileBadgeSourceInterface;
use App\Application\Profiles\DTOs\ProfileBadgeDTO;
use App\Domain\Profiles\ProfileBadgeColour;

final readonly class FeaturedAchievementBadges implements ProfileBadgeSourceInterface
{
    public function __construct(private FeaturedAchievementsQuery $featured) {}

    public function badgesFor(array $userIds): array
    {
        $badges = [];

        // One query for every account of the page, never one per account
        foreach ($this->featured->forUsers($userIds) as $row) {
            $badges[$row->user_id][] = new ProfileBadgeDTO(
                key: 'achievements.'.$row->slug,
                label: $row->name,
                description: $row->description,
                icon: $row->icon,                          // one of ProfileBadgeIcons::NAMES
                colour: ProfileBadgeColour::from($row->colour),
                imageUrl: $row->image_url,                 // https (Cloudinary), or null
                url: route('achievements.show', $row->slug, absolute: false),
            );
        }

        return $badges;
    }
}

// In the module's service provider
public function registerProfileBadgeSources(): array
{
    return [FeaturedAchievementBadges::class];
}
```

- **Batched.** `badgesFor(list<string> $userIds): array<string, list<ProfileBadgeDTO>>` gets every account of a page at once (in lower case, each once) and answers all of them with one query. An account with no badge may be left out; accounts it was not asked about are ignored.
- **The core decided already.** The core asks a source only about accounts whose profile the person viewing may see (`ProfileAccessRule`), and the source is never told who is viewing: a badge is part of the profile, not of a page with its own permissions. So a badge never says anything the owner keeps private elsewhere (a badge «Ganó el torneo X» of a tournament the viewer could not see is not a featured badge).
- **At most three per account** (`ProfileBadgesInterface::MAX_PER_ACCOUNT`), in the owner's order. Sources are asked in the order they were registered and the first three badges drawable win; a source that gives more than three is cut, with a warning.
- **Built per request.** The registry keeps classes, never instances; the source is built from the container once per request or job.

`ProfileBadgeDTO` (`App\Application\Profiles\DTOs`), checked by the core before anybody sees it:

| Field | Rule (a badge that breaks it is left out, with a warning) |
|---|---|
| `key` | `<module>.<badge>` of the source's own module, with the rules of a section key (`ProfileKey`); unique among one account's badges (a repeated key is left out). |
| `label` | The badge's name, translated: its accessible name. Plain text: control characters and bidirectional marks are dropped, blanks collapse to one space, cut to 60 characters (`LABEL_MAX_LENGTH`). Empty after that: left out. |
| `description` | What it was given for, translated; may be empty. Same cleaning, cut to 200 characters (`DESCRIPTION_MAX_LENGTH`). |
| `icon` | One of `App\Application\Profiles\ProfileBadgeIcons::NAMES` (Lucide names): `award`, `book-open`, `calendar-check`, `castle`, `compass`, `crown`, `dices`, `feather`, `flag`, `flame`, `gem`, `hand-heart`, `handshake`, `heart`, `hourglass`, `map`, `map-pin`, `medal`, `message-square`, `mountain`, `puzzle`, `rocket`, `scroll-text`, `shield`, `sparkles`, `sprout`, `star`, `sword`, `swords`, `target`, `ticket`, `trophy`, `users`, `vote`, `wand-sparkles`, `zap`. Check yours in tests with `ProfileBadgeIcons::isKnown()`. |
| `colour` | `App\Domain\Profiles\ProfileBadgeColour`: `slate`, `red`, `orange`, `amber`, `green`, `teal`, `sky`, `blue`, `violet`, `pink`. Each is a 700 shade with white text in the light scheme and a 300 shade with dark text in the dark one: at least 4.5:1 in both. |
| `imageUrl` | Optional, an absolute `https` address (never a path or `http`): drawn cropped to a square instead of the icon. Use a Cloudinary URL (the site's content security policy allows images from Cloudinary) cropped to a square: it is drawn at 24 × 24 px, so 48 × 48 is enough for dense screens. |
| `url` | Optional, where the badge links to: an absolute `https` address or a path of the site (`LinkTargetRule`). |

### Showing authors' badges: `ProfileBadgesInterface::forAuthors()`

A module that shows authors (a forum thread, a list of comments) asks the core once per page:

```php
use App\Application\Profiles\DTOs\ProfileBadgeDTO;
use App\Application\Profiles\Services\ProfileBadgesInterface;

// ProfileBadgesInterface $badges, injected anywhere (it asks for the request's rows on each call)
$byAuthor = $this->badges->forAuthors($authorIds, $request->user());

$posts = array_map(fn ($post): array => [
    // ...
    'authorBadges' => array_map(
        static fn (ProfileBadgeDTO $badge): array => $badge->toArray(),
        $byAuthor[strtolower($post->authorId)] ?? [],
    ),
], $page);
```

- `forAuthors(list<string> $userIds, ?Authenticatable $viewer): array<string, list<ProfileBadgeDTO>>`: account id in lower case => badges, in the order of the ids; an account without badges is left out. Repeated ids and anything that is not an account id are ignored.
- **One call per page**: the profile rows and the accounts are read with one query each, the access rule asks about the viewer once, and each source is asked once. With no source registered (no module gives badges) it reads nothing.
- **Only what the viewer may see**: nothing for a profile that is hidden from them, deactivated, anonymized, without a profile row or without a public name (except for its owner).
- Never for an author the page does not show as such (a hidden NPC author, an anonymous post): the call decides who may see the profile, not whether showing that this account wrote something is allowed.
- It never throws: any failure gives no badges (a warning with the exception's class only).

The profile page (`header.badges`) and the card (`badges`) already carry the owner's badges: do not ask for them again there.

### Drawing them: `ProfileBadges.vue`

Public frontend API (`@/components/ui/ProfileBadges.vue`), types in `@/types/profileSections` (`ProfileBadge`, `ProfileBadgeColour`, `ProfileBadgeSize`):

```vue
<script setup lang="ts">
import ProfileBadges from '@/components/ui/ProfileBadges.vue';
import type { ProfileBadge } from '@/types/profileSections';

defineProps<{ authorName: string; authorBadges: ProfileBadge[] }>();
</script>

<template>
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
        <span class="font-semibold">{{ authorName }}</span>
        <ProfileBadges :badges="authorBadges" size="sm" />
    </div>
</template>
```

| Prop | Meaning |
|---|---|
| `badges` | `ProfileBadge[]`, as the server sent them. Draws nothing for an empty list. |
| `size` | `'md'` (default): icon or image and label, 32 px high, for the profile and the card. `'sm'`: 24 px icons alone, for a post header; the label is still the accessible name and shows in the tooltip. |

- A list (`<ul>`, labelled «Insignias destacadas») that wraps on narrow screens.
- With `imageUrl` the image (cropped to a circle), otherwise the icon on the badge's colour; an icon the browser does not know is drawn as `award`.
- **Accessible.** The label is the badge's name (visible in `md`, `sr-only` or the image's `alt` in `sm`). A badge with a description, or any `sm` badge, is a focusable button (a link when it has `url`) whose description is its `aria-describedby`, so a screen reader reads it; it also shows as a tooltip on hover, on keyboard focus and on a tap, and closes with Escape, a tap elsewhere, or when the pointer or focus leaves. A badge with nothing more to say is plain text.
- Links to the site go through Inertia; any other address is a plain link.

## Failing closed

A section or stat never breaks the profile around it:

| Situation | What happens |
|---|---|
| The class cannot be built, is not a `ProfileSectionInterface` / `ProfileStatInterface`, its key is invalid, or (sections) its component path is invalid or `region()` throws | Left out of the registry, warning |
| Built again when drawing (once per request or job, only when on), it cannot be built, has another key than when it was registered or (sections) an invalid component path | Not drawn, warning |
| The same class registered by a second owner | The second registration is ignored, warning |
| The key does not start with the module's own name (`core.` for the core), or a module called `core` | Left out, warning |
| Two sections (or two stats) with the same key | Both left out, warning (once per key) |
| A key the owner turned on that nobody registers (its module disabled or uninstalled) | Not drawn, warning (only for something shaped like a key: free text in a layout is never logged) |
| Turned off by the owner, or off by default and never turned on | Not drawn, no warning |
| `isAvailable()` is false | Not drawn, no warning |
| `props()` throws `NothingToDrawException`, or `stat()` returns `null` | Not drawn, no warning: a section's title is not left alone either |
| `isAvailable()`, `props()` or `stat()` throws, props are not keyed by name or cannot be encoded as JSON, a negative count, or a link `LinkTargetRule` refuses | Not drawn, warning |
| The Vue component fails to load or render | `ProfileSection.vue` drops it, title included (`onErrorCaptured`, like `SiteBlock.vue`) |
| The profile row or the account cannot be read, or the membership check throws | The header is drawn without what failed (no badge), the layout takes the defaults, warning |
| A badge source cannot be built, is not a `ProfileBadgeSourceInterface` or throws | It gives no badges; the other sources still give theirs, warning |
| A source answers an account with something that is not a list, or gives something that is not a `ProfileBadgeDTO` | That account's answer (or that item) is left out, warning |
| A badge breaks a rule of `ProfileBadgeDTO` (key, label, icon, image, link) or repeats a key | That badge is left out, warning |
| A source gives more than three badges for an account | Only the first three, warning |

Under php-fpm every web request builds the registries and the renderer again; a queue worker keeps the registries (singletons, holding only classes and what was read from them) for its whole life and gets a fresh renderer, with freshly built sections and stats, for each job. Either way warnings are kept apart for a day in the cache, not in memory (`App\Infrastructure\Support\DailyWarnings`, prefix `profiles:warned:`, the same mechanism as site blocks): the registries' per class and reason (a duplicated key, per key), the renderer's per key and reason, the badges' per source class and reason. Warnings carry the class, the module, the key and the reason; props, counts and exception messages are never logged, and an exception is named only by its class.

## The core's section

`core.about` («Sobre mí», `App\Infrastructure\Profiles\Sections\AboutProfileSection`): the owner's bio, in the aside, on by default. It is plain text: the server cuts it into lines and pieces of text and links (`App\Domain\Profiles\ProfileBioText`), and `AboutSection.vue` draws every piece as text, so nothing the owner wrote is ever drawn as HTML. At most 500 characters are drawn (`ProfileBioText::MAX_LENGTH`, the editor's limit). Line breaks are kept (a run of blank lines counts as one); control characters (C0 and C1) and the invisible direction marks (U+202A–U+202E, U+2066–U+2069, U+200E, U+200F, U+061C) are dropped, so a link cannot be made to read as another. Only `http` and `https` addresses become links: the host is letters, digits, dots and hyphens (an international name only in its `xn--` form), with no user name before it and no backslash anywhere; quotes of any kind (`«»“”‘’`) end an address, and the sentence's punctuation, an ellipsis or an unbalanced bracket at its end stay out. An address the 500-character cut splits stays text, since cut short it could name another host (`https://bank.example.evil.com` → `https://bank.example`). Links are drawn with `rel="nofollow ugc noopener"`. An empty bio draws nothing.

## Testing

- Module tests: register the section and render a profile as the core will (in tests only: module code never calls the renderer).

```php
$this->app->make(ProfileSectionRegistryInterface::class)->register(LatestPostsProfileSection::class, 'forum');

$profile = $this->app->make(ProfileRendererInterface::class)
    ->render(new ProfileOwnerDTO($owner->id, 'ana-garcia', 'Ana García'), $viewer);

$this->assertSame(['forum.latest_posts'], array_map(fn ($section) => $section->key, $profile->main));
```

- Test the checklist, not only the happy path: a guest, a signed-in account and a moderator see what each sees on the module's own pages; a hidden category, an NPC author or a private sign-up never shows or counts; nothing to show throws `NothingToDrawException` (or returns `null` for a stat).
- Vue specs mount the module's component with the props `props()` returns; `ProfileSection.vue` itself is covered by the core.
- Badge sources: register the source and ask as a page would; check the badges, that a source answers every account with one query, and that every icon is known.

```php
$this->app->make(ProfileBadgeSourceRegistryInterface::class)->register(FeaturedAchievementBadges::class, 'achievements');

$badges = $this->app->make(ProfileBadgesInterface::class)->forAuthors([$ana->id, $berta->id], $viewer);

$this->assertSame(['achievements.first-game'], array_map(fn (ProfileBadgeDTO $badge) => $badge->key, $badges[$ana->id]));
$this->assertTrue(ProfileBadgeIcons::isKnown($badges[$ana->id][0]->icon));
```
