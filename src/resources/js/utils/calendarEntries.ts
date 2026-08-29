import type { EventInput } from '@fullcalendar/core';
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

function expandEntry(entry: CalendarEntry): CalendarEntry[] {
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
    let dayKey = startDayKey;
    let dayIndex = 0;

    // 'YYYY-MM-DD' keys compare lexicographically in chronological order, so the
    // whole walk stays on venue days without any browser-local date arithmetic.
    while (dayKey <= lastDayKey && dayIndex < MAX_EXPANDED_DAYS) {
        pieces.push({
            ...entry,
            id: `${entry.id}:${dayKey}`,
            // Continuation days are markers, not real instants. Anchoring them at
            // UTC midnight keeps them inside the same venue day (Madrid is UTC+1/+2)
            // and sorts them ahead of that day's timed entries, as before.
            start: dayIndex === 0 ? entry.start : `${dayKey}T00:00:00Z`,
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
 * FullCalendar's compact `eventDisplay: 'list-item'` mode only ever renders
 * a segment on the entry's *start* day; continuation days get no harness at
 * all (verified in-browser), so a multi-day entry would otherwise show a
 * single dot instead of one dot per day it spans. This works around that by
 * pre-slicing multi-day entries into day-sized pieces before they reach
 * FullCalendar. Intended for compact (widget) mode only -- the block view
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
 * - `start`: the original start time on the first day; a UTC-midnight anchor
 *   for the covered venue day on every following day (see `expandEntry`).
 *   These continuation values are day markers, not real instants.
 * - `end`: always `null` -- each piece is a single-day marker, not a source
 *   of truth for the entry's real duration. Callers that need the real
 *   start/end (tooltips, detail panels) must look them up via the ORIGINAL
 *   entry, not the synthetic piece (see EventCalendar.vue's eventsCache,
 *   which maps every synthetic id back to the original entry).
 *
 * A single entry is capped at `MAX_EXPANDED_DAYS` synthetic pieces as a
 * defensive guard against corrupt/unbounded date ranges.
 */
export function expandEntriesPerDay(entries: CalendarEntry[]): CalendarEntry[] {
    return entries.flatMap((entry) => expandEntry(entry));
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
 * The color token travels as a class name (`fc-entry--{color}`) so the
 * styling is resolved by CSS variables instead of inline colors.
 */
export function toFullCalendarEvents(entries: CalendarEntry[]): EventInput[] {
    return entries.map((entry) => {
        const baseEvent: EventInput = {
            id: entryUid(entry),
            title: entry.title,
            start: entry.start,
            url: entry.url,
            classNames: ['fc-entry', `fc-entry--${entry.color}`],
        };

        return entry.end !== null ? { ...baseEvent, end: entry.end } : baseEvent;
    });
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
