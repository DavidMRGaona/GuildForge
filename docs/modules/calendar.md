# Calendar sources, the public feed and the personal calendar

The public calendar (`/calendario` and the home widget) shows core events and whatever the enabled modules add. Each person who is signed in also has a personal calendar: the «Solo en lo que estoy inscrito» filter on `/calendario`, and a private subscription feed for Google Calendar, Apple Calendar, Outlook and other apps. Anyone, with or without an account, can subscribe to the public calendar too (`/calendario.ics`, core ≥ 3.40.0). This page explains the three contracts a module can implement and the one it can use to show the public feed. For the rest of the module SDK, see the [module development guide](./README.md).

| Contract | Hook | What it gives |
|----------|------|---------------|
| `CalendarEntrySourceInterface` | `registerCalendarSources()` | The module's entries in the public calendar |
| `CalendarEntryLookupInterface` (optional, on the same source) | — | Downloading one entry as `.ics` and the «Añadir al calendario» links (core ≥ 3.37.0) |
| `CalendarAttendanceSourceInterface` | `registerCalendarAttendanceSources()` | Which entries each person takes part in, for their personal calendar (core ≥ 3.38.0) |
| `PublicCalendarFeedInterface` (to use, not to implement) | — | The address of the public feed and its subscription links (core ≥ 3.40.0) |

The source contracts live in `App\Application\Calendar\Contracts`, the services in `App\Application\Calendar\Services`. A module never depends on another module: it may point at any source's entries (the core's `event`, say), but only through these contracts.

## Public calendar entries

A source implements `CalendarEntrySourceInterface`:

| Method | Returns |
|--------|---------|
| `sourceType()` | Stable identifier of the entries (`game-table`). It is part of the `.ics` UID and of the personal calendar references: never rename it |
| `sourceLabel()` | Translated name shown in the legend («Partidas») |
| `color()` | A `CalendarSourceColor` token |
| `findByDateRange(from, to)` | The `CalendarEntryDTO`s that overlap `[from, to)` |

- Both bounds are instants in the storage timezone; compare stored values against them directly.
- Return only what the public calendar may show: published, not cancelled, not a draft.
- `CalendarEntryDTO::$id` is the id the other contracts use for the entry. Use the record's key as a string.
- Fill `details` with `description`, `location`, `imagePublicId` and `meta` rows (`icon`, `label`, `value`, translated) so the detail panel is as complete as an event's.
- A source that throws is logged and skipped: the calendar never answers 500 because of one module.
- Never look at the signed-in person (`auth()`, the request, the session): the same entries feed the public subscription, which runs without a session and is cached for everyone.

## One entry by id (add to calendar)

The same source may also implement `CalendarEntryLookupInterface::findEntry(string $id): ?CalendarEntryDTO`. Then each entry can be downloaded at `/calendario/{sourceType}/{id}.ics`, and `AddToCalendarLinksFactoryInterface` adds the `.ics` link to the Google Calendar and Outlook ones (`AddToCalendarMenu.vue` shows them on the module's pages).

- Return the entry only when the public calendar would show it, and `null` for anything else, including ids that are not well formed: the download answers 404 then.

## The personal calendar

A module that knows who takes part in what implements `CalendarAttendanceSourceInterface`:

```php
<?php

declare(strict_types=1);

namespace Modules\MyModule\Infrastructure\Calendar;

use App\Application\Calendar\Contracts\CalendarAttendanceSourceInterface;
use App\Application\Calendar\DTOs\CalendarEntryReferenceDTO;
use DateTimeImmutable;
use Modules\MyModule\Infrastructure\Persistence\Eloquent\Models\SignUpModel;

final readonly class MyModuleAttendanceSource implements CalendarAttendanceSourceInterface
{
    /**
     * @return array<CalendarEntryReferenceDTO>
     */
    public function attendedEntries(string $userId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return SignUpModel::query()
            ->where('user_id', $userId)
            ->where('status', 'confirmed')
            ->whereHas('table', fn ($query) => $query
                ->where('starts_at', '<', $to)
                ->where('ends_at', '>', $from))
            ->pluck('table_id')
            ->map(static fn (string $id): CalendarEntryReferenceDTO => new CalendarEntryReferenceDTO('my-module-table', $id))
            ->all();
    }
}
```

Register it from the service provider, and raise `requires.core` in `module.json` to `^3.38`: an older core does not know the hook and the module would simply not appear in anyone's personal calendar.

```php
/** @return array<class-string<CalendarAttendanceSourceInterface>> */
public function registerCalendarAttendanceSources(): array
{
    return [MyModuleAttendanceSource::class];
}
```

Rules:

- **Return references, not entries.** A `CalendarEntryReferenceDTO($sourceType, $id)` names an entry of the public calendar by its source type and its `CalendarEntryDTO::$id`. It may point at another source: a tournament participant gets `new CalendarEntryReferenceDTO('event', $eventId)`, because each tournament belongs to one core event.
- **Only what the person is confirmed in or runs.** Leave out pending, waitlisted, rejected and cancelled sign-ups. The organiser or director of an entry counts as taking part.
- **Same range as the public calendar.** `[from, to)`, instants in the storage timezone. The filter asks for the month on screen; the feed asks from 30 days back to 12 months ahead.
- **The core keeps only what the public calendar shows.** It reads the public calendar for the same range and keeps the referenced entries, so a reference to a draft, a cancelled or an unknown entry is dropped. Do not repeat the publication rules here; do not try to show more than the public calendar either.
- **No data about other people.** A reference carries two ids and nothing else; the feed is written from the public entry alone.
- Duplicates are removed (two modules may reference the same event). A source that throws, or that cannot be built, is logged and skipped; anything in the array that is not a `CalendarEntryReferenceDTO` is ignored.
- One query per call: the method runs on every calendar month a person opens with the filter on, and on every feed refresh.

What the core does with it:

- `PersonalCalendarServiceInterface::entriesFor($userId, $from, $to)` joins every source; `isAvailable()` is true while some attendance source is registered. Without one, the filter, the profile tab and the help guide (capability `core.personal_calendar`) are hidden.
- `GET /eventos/calendario?mine=1` answers the signed-in person's entries (401 for guests, the email verification 403 when the account must verify), never cached by shared caches.
- `/calendario/personal/{token}.ics` is the private feed. The token is 32 random bytes, stored only as a SHA-256 hash, issued and revoked from the profile («Mi calendario» tab), dropped when the account is anonymized, and refused (404) for deactivated or anonymized accounts and for accounts that must verify their email. It is rate limited per address.

Test it with a feature test that calls `PersonalCalendarServiceInterface::entriesFor()` for a person with a confirmed sign-up, a pending one, one in a cancelled entry and someone else's: only the first comes back.

## The public feed

`/calendario.ics` (route `calendar.public.ics`, core ≥ 3.40.0) is the public calendar as an iCalendar subscription: every entry of every source from 30 days back to 12 months ahead, the same range as the personal feed, named «Calendario · {site}». Calendar apps fetch it again on their own, so new entries, time changes and cancellations reach subscribers without them doing anything.

- It is the same document for everyone. The route runs without a session (no cookies), the document is built once every 5 minutes and kept in the cache, and the answer is `Cache-Control: public, max-age=300` with an ETag (a repeated `If-None-Match` gets 304). An unreachable cache only means writing it again.
- 60 requests a minute per address (`throttle:calendar-public-feed`): calendar services fetch everyone's subscriptions from a few shared addresses.
- In maintenance mode it answers 503 in plain text with `Retry-After`, like every route without a session.
- `/calendario` shows a «Suscribirse» button with the subscription links.

A module that shows the feed (a link page, a poster with a QR code) asks `App\Application\Calendar\Services\PublicCalendarFeedInterface` and never builds the address by hand:

| Method | Returns |
|--------|---------|
| `feedUrl()` | Absolute https address of the feed (`https://example.test/calendario.ics`) |
| `calendarName()` | The name calendar apps show for it, with the site's name |
| `subscriptionLinks()` | `CalendarSubscriptionLinksDTO`: `feedUrl`, `webcalUrl` (`webcal://…/calendario.ics`, for Apple Calendar, Outlook and ICSx⁵), `googleUrl` and `outlookUrl` (one-tap pages: browsers other than Safari do not hand `webcal://` to an app); `toArray()` gives the same keys for an Inertia prop |

```php
use App\Application\Calendar\Services\PublicCalendarFeedInterface;

public function __invoke(PublicCalendarFeedInterface $calendarFeed): Response
{
    return Inertia::render('MyModule::Links', [
        'calendarFeed' => $calendarFeed->subscriptionLinks()->toArray(),
    ]);
}
```

Raise `requires.core` in `module.json` to `^3.40` before using it. The same links for any other feed address come from `CalendarSubscriptionLinksFactoryInterface::for($feedUrl, $calendarName)`, which the personal feed uses too. On the frontend the type is `CalendarSubscriptionLinks` (`@/types/calendarFeed`).

## The calendar on a module page

A module page can show the site calendar (core events and every public source) with `ModuleEventCalendar.vue`, a public API entry (core ≥ the release with #349):

```vue
<script setup lang="ts">
import ModuleEventCalendar from '@/components/calendar/ModuleEventCalendar.vue';
</script>

<template>
    <ModuleEventCalendar initial-view="list" :views="['list', 'month']" />
</template>
```

| Prop | Default | What it does |
|------|---------|--------------|
| `initialView` | `'month'` | View shown first: `'month'` (the month grid) or `'list'` (the month's entries as a list, easier to read on a phone) |
| `views` | `[]` | Views offered as toolbar buttons («Lista», «Mes»); with fewer than two there are none. The initial view is always in |
| `height` | `'auto'` | Height in pixels, or `'auto'` to grow with the content |
| `legend` | `true` | Lists the activity types and their colours below the calendar |

Clicking an entry opens its page. The look is the shared one (`calendarTheme.ts`, styles in the core's `app.css`), so the page needs nothing else. A module building its own FullCalendar can import the list plugin from `@fullcalendar/vue3/list`, a shared package like `daygrid` and `timegrid`.

A module's own FullCalendar gets the shared toolbar by spreading `calendarClassOptions` (buttons 44 px tall, ‹ › joined, the views as a segmented control) and `calendarToolbarOptions(viewButtons)` (core ≥ the release with #423) instead of its own `headerToolbar`. That helper puts the title and ‹ › on one row and «Hoy» with the view buttons below it, or everything on one row once the calendar is 34rem wide:

```ts
import { calendarClassOptions, calendarToolbarOptions } from '@/utils/calendarTheme';

const options: CalendarOptions = {
    ...calendarClassOptions,
    ...calendarToolbarOptions('dayGridMonth,timeGridWeek,timeGridDay'),
    // the module's own options
};
```

Wrap the calendar in an element with the `event-calendar` class, as `ModuleEventCalendar` does, so the styles apply.

## Upcoming core events

`EventQueryServiceInterface::getUpcomingEventsWithin(UpcomingEventsCriteriaDTO $criteria)` (core ≥ the release with #349) returns the published core events (`EventResponseDTO`) starting from now, soonest first:

```php
$events = $eventQuery->getUpcomingEventsWithin(
    new UpcomingEventsCriteriaDTO(limit: 5, withinDays: 30, tagSlugs: ['wargames']),
);
```

- `limit` and `withinDays` must be at least 1 (`InvalidArgumentException` otherwise).
- `withinDays` counts display days, today being the first: 1 is the rest of today, 7 today and the six days after it. The horizon ends at the following midnight in the display timezone.
- `tagSlugs` keeps the events with any of the tags; without it there is no tag filter. Blank and repeated slugs are dropped.
- Events that have already started are not included, as in `getUpcomingEvents()`.
