import type { EventInput } from '@fullcalendar/vue3';
import type { CalendarEntry, CalendarSourceColor } from '@/types/models';
import { VENUE_TIMEZONE, addVenueDays, venueDayKey } from '@/utils/datetime';

export interface CalendarLegendItem {
    sourceType: string;
    sourceLabel: string;
    color: CalendarSourceColor;
}

/**
 * Build a unique cache/DOM id for a calendar entry.
 *
 * Different source types (events, game tables, ...) may reuse the same
 * numeric/uuid id space, so the source type is prefixed to avoid collisions
 * in the events cache and in FullCalendar's internal event map.
 */
export function entryUid(entry: Pick<CalendarEntry, 'id' | 'sourceType'>): string {
    return `${entry.sourceType}:${entry.id}`;
}

/**
 * Return the calendar day at the venue for an ISO date string.
 *
 * Events happen on site, so the day an entry belongs to is the day it falls on
 * in the venue's timezone -- not in the visitor's. Deriving it from the
 * browser's local getters would move late-evening entries to a neighbouring day
 * for anyone browsing from another timezone.
 */
export function entryDayKey(iso: string): string {
    return venueDayKey(new Date(iso));
}

/** Safety cap on the number of synthetic per-day pieces emitted for a single
 *  multi-day entry, in case of corrupt/unbounded date ranges (~2 months). */
const MAX_EXPANDED_DAYS = 62;

const venueTimeFormatter = new Intl.DateTimeFormat('en-GB', {
    timeZone: VENUE_TIMEZONE,
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hourCycle: 'h23',
});

function isExactVenueMidnight(date: Date): boolean {
    // Milliseconds are timezone-independent, so they can be read off directly.
    return date.getMilliseconds() === 0 && venueTimeFormatter.format(date) === '00:00:00';
}

const MINUTE_MS = 60_000;
// Any venue day starts between 14 hours before and 12 hours after UTC midnight of
// the same date (UTC+14 to UTC-12); one more hour each side leaves room to search.
const DAY_START_EARLIEST_MINUTES = -15 * 60;
const DAY_START_LATEST_MINUTES = 13 * 60;

const venueDayStartCache = new Map<string, string>();

/**
 * First instant of a venue day ('YYYY-MM-DD'), as an ISO string.
 *
 * Usually venue midnight, but not on a day whose clocks jump over midnight
 * (America/Santiago, America/Havana on their DST day go from 23:59 straight to
 * 01:00): there it is 01:00. It is found by searching the minute where the venue
 * day begins, since time zone offsets and their changes fall on whole minutes.
 */
function venueDayStart(dayKey: string): string {
    const cached = venueDayStartCache.get(dayKey);
    if (cached !== undefined) {
        return cached;
    }

    const utcMidnight = Date.parse(`${dayKey}T00:00:00Z`);
    // Invariant: the venue day at `low` is before `dayKey`, at `high` it is not.
    let low = DAY_START_EARLIEST_MINUTES;
    let high = DAY_START_LATEST_MINUTES;

    while (high - low > 1) {
        const middle = Math.floor((low + high) / 2);

        if (venueDayKey(new Date(utcMidnight + middle * MINUTE_MS)) < dayKey) {
            low = middle;
        } else {
            high = middle;
        }
    }

    const start = new Date(utcMidnight + high * MINUTE_MS).toISOString();
    venueDayStartCache.set(dayKey, start);

    return start;
}

function expandEntry(entry: CalendarEntry, fromDayKey: string | undefined): CalendarEntry[] {
    if (entry.end === null) {
        return [entry];
    }

    const startDayKey = entryDayKey(entry.start);
    const endDayKey = entryDayKey(entry.end);

    if (startDayKey === endDayKey) {
        return [entry];
    }

    // Half-open range: an `end` that lands exactly at venue midnight marks
    // the close of the *previous* day, so that calendar day is excluded.
    const lastDayKey = isExactVenueMidnight(new Date(entry.end))
        ? addVenueDays(endDayKey, -1)
        : endDayKey;

    if (lastDayKey < startDayKey) {
        // Degenerate/corrupt range after excluding the midnight end day;
        // fall back to the original entry rather than silently dropping it.
        return [entry];
    }

    const pieces: CalendarEntry[] = [];
    // Days before the visible range need no marker, and skipping them keeps a long
    // entry that started months ago from spending the whole cap before reaching it.
    let dayKey = fromDayKey !== undefined && fromDayKey > startDayKey ? fromDayKey : startDayKey;
    let dayIndex = 0;

    // 'YYYY-MM-DD' keys compare lexicographically in chronological order, so the
    // whole walk stays on venue days without any browser-local date arithmetic.
    while (dayKey <= lastDayKey && dayIndex < MAX_EXPANDED_DAYS) {
        pieces.push({
            ...entry,
            id: `${entry.id}:${dayKey}`,
            // Continuation days are markers, not real instants. They are anchored at
            // the first instant of their venue day, which lies inside that day in any
            // zone and is never later than the day's timed entries. UTC midnight only
            // worked ahead of UTC: behind it, it is still the previous day (core#690).
            start: dayKey === startDayKey ? entry.start : venueDayStart(dayKey),
            end: null,
        });

        dayKey = addVenueDays(dayKey, 1);
        dayIndex += 1;
    }

    return pieces;
}

/**
 * Expand multi-day calendar entries into one synthetic, single-day marker
 * per covered venue day.
 *
 * The compact widget draws one dot per entry on the day the entry starts
 * (see `toCompactDayEvents`), so a multi-day entry would otherwise show a
 * single dot instead of one dot per day it spans. Pre-slicing multi-day
 * entries into day-sized pieces gives every covered day its own dot.
 * Intended for compact (widget) mode only -- the block view
 * on `/calendario` already renders multi-day spans correctly and must not
 * be expanded.
 *
 * Day inclusion for the `end` boundary follows the usual half-open range
 * convention: the day of `start` is always included; the day of `end` is
 * included UNLESS `end` falls exactly at venue midnight (00:00:00.000), in
 * which case that day is excluded (the event effectively finished at the
 * close of the previous day). Entries with `end === null`, or whose `start`
 * and `end` fall on the same venue day, pass through unchanged.
 *
 * Each synthetic piece keeps every field of the original entry except:
 * - `id`: suffixed with the covered day (`${id}:${dayKey}`) so `entryUid`
 *   stays unique per day and doesn't collide with the original id or with
 *   other days of the same entry.
 * - `start`: the original start time on the first day; on every following
 *   day, the first instant of that venue day (venue midnight, or 01:00 where
 *   the clocks skip midnight; see `venueDayStart`). These continuation values
 *   are day markers, not real instants: they put the piece on its day and sort
 *   it ahead of that day's timed entries (a tie with an entry at 00:00 keeps
 *   the one given first; the server sends entries in start order, so the
 *   multi-day one).
 * - `end`: always `null` -- each piece is a single-day marker, not a source
 *   of truth for the entry's real duration. Callers that need the real
 *   start/end (tooltips, detail panels) must look them up via the ORIGINAL
 *   entry, not the synthetic piece (see EventCalendar.vue's eventsCache,
 *   which maps every synthetic id back to the original entry).
 *
 * `fromDayKey` (a venue day, `YYYY-MM-DD`) is the first day on screen: no
 * pieces are emitted before it, so an entry that started long before the
 * visible range still gets its dots there.
 *
 * A single entry is capped at `MAX_EXPANDED_DAYS` synthetic pieces as a
 * defensive guard against corrupt/unbounded date ranges.
 */
export function expandEntriesPerDay(
    entries: CalendarEntry[],
    fromDayKey?: string
): CalendarEntry[] {
    return entries.flatMap((entry) => expandEntry(entry, fromDayKey));
}

/**
 * Collapse calendar entries per day to avoid dot overload in compact views.
 *
 * - If a day only has entries of a single activity type, all of them are kept
 *   (current behaviour: one dot per entry).
 * - If a day mixes several activity types, only the earliest entry of each
 *   type is kept (at most one dot per activity type).
 */
export function collapseEntriesByActivityType(entries: CalendarEntry[]): CalendarEntry[] {
    const entriesByDay = new Map<string, CalendarEntry[]>();

    for (const entry of entries) {
        const dayKey = entryDayKey(entry.start);
        const dayEntries = entriesByDay.get(dayKey);

        if (dayEntries) {
            dayEntries.push(entry);
        } else {
            entriesByDay.set(dayKey, [entry]);
        }
    }

    const collapsed: CalendarEntry[] = [];

    for (const dayEntries of entriesByDay.values()) {
        const sourceTypes = new Set(dayEntries.map((entry) => entry.sourceType));

        if (sourceTypes.size <= 1) {
            collapsed.push(...dayEntries);
            continue;
        }

        const earliestByType = new Map<string, CalendarEntry>();

        for (const entry of dayEntries) {
            const current = earliestByType.get(entry.sourceType);
            if (!current || new Date(entry.start).getTime() < new Date(current.start).getTime()) {
                earliestByType.set(entry.sourceType, entry);
            }
        }

        collapsed.push(...earliestByType.values());
    }

    return collapsed;
}

/**
 * Map calendar entries to FullCalendar's EventInput shape.
 *
 * The colours are CSS variable references, not values: FullCalendar copies
 * them into each event's `--fc-event-color` / `--fc-event-contrast-color`, and
 * the browser resolves them against `--calendar-entry-*` (resources/css/app.css),
 * which follow the site's configured colours and dark mode.
 */
export function toFullCalendarEvents(entries: CalendarEntry[]): EventInput[] {
    return entries.map((entry) => {
        const baseEvent: EventInput = {
            id: entryUid(entry),
            title: entry.title,
            start: entry.start,
            url: entry.url,
            color: `var(--calendar-entry-${entry.color})`,
            contrastColor: `var(--calendar-entry-${entry.color}-text)`,
        };

        return entry.end !== null ? { ...baseEvent, end: entry.end } : baseEvent;
    });
}

/**
 * Group compact (widget) entries into one FullCalendar event per venue day.
 *
 * FullCalendar 7 stacks a day's events in rows, one below the other, and
 * re-measures them whenever their layout is overridden. The widget therefore
 * hands it a single event per day and draws the dots of all that day's entries
 * side by side inside it (EventCalendar's `eventContent` slot). The uids of the
 * day's entries travel in `extendedProps.entryUids`, in the order given.
 */
export function toCompactDayEvents(entries: CalendarEntry[]): EventInput[] {
    const uidsByDay = new Map<string, string[]>();

    for (const entry of entries) {
        const dayKey = entryDayKey(entry.start);
        const uids = uidsByDay.get(dayKey);

        if (uids) {
            uids.push(entryUid(entry));
        } else {
            uidsByDay.set(dayKey, [entryUid(entry)]);
        }
    }

    return Array.from(uidsByDay, ([dayKey, entryUids]) => ({
        id: `day:${dayKey}`,
        start: dayKey,
        allDay: true,
        extendedProps: { entryUids },
    }));
}

/**
 * Extract the unique activity types present in a set of entries, keeping
 * their label and color, for use as a data-driven legend.
 */
export function extractLegend(entries: CalendarEntry[]): CalendarLegendItem[] {
    const legendBySourceType = new Map<string, CalendarLegendItem>();

    for (const entry of entries) {
        if (!legendBySourceType.has(entry.sourceType)) {
            legendBySourceType.set(entry.sourceType, {
                sourceType: entry.sourceType,
                sourceLabel: entry.sourceLabel,
                color: entry.color,
            });
        }
    }

    return Array.from(legendBySourceType.values());
}
