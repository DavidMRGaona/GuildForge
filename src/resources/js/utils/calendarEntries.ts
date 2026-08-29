import type { EventInput } from '@fullcalendar/core';
import type { CalendarEntry, CalendarSourceColor } from '@/types/models';

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
 * Return the calendar day (in the browser's local timezone) for an ISO date string.
 */
export function localDayKey(iso: string): string {
    const date = new Date(iso);
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

/** Safety cap on the number of synthetic per-day pieces emitted for a single
 *  multi-day entry, in case of corrupt/unbounded date ranges (~2 months). */
const MAX_EXPANDED_DAYS = 62;

function startOfLocalDay(date: Date): Date {
    return new Date(date.getFullYear(), date.getMonth(), date.getDate());
}

function isExactLocalMidnight(date: Date): boolean {
    return (
        date.getHours() === 0 &&
        date.getMinutes() === 0 &&
        date.getSeconds() === 0 &&
        date.getMilliseconds() === 0
    );
}

function expandEntry(entry: CalendarEntry): CalendarEntry[] {
    if (entry.end === null) {
        return [entry];
    }

    const startDayKey = localDayKey(entry.start);
    const endDayKey = localDayKey(entry.end);

    if (startDayKey === endDayKey) {
        return [entry];
    }

    const startDate = new Date(entry.start);
    const endDate = new Date(entry.end);

    const firstDay = startOfLocalDay(startDate);
    const lastDay = startOfLocalDay(endDate);

    // Half-open range: an `end` that lands exactly at local midnight marks
    // the close of the *previous* day, so that calendar day is excluded.
    if (isExactLocalMidnight(endDate)) {
        lastDay.setDate(lastDay.getDate() - 1);
    }

    if (lastDay.getTime() < firstDay.getTime()) {
        // Degenerate/corrupt range after excluding the midnight end day;
        // fall back to the original entry rather than silently dropping it.
        return [entry];
    }

    const pieces: CalendarEntry[] = [];
    const cursor = new Date(firstDay);
    let dayIndex = 0;

    while (cursor.getTime() <= lastDay.getTime() && dayIndex < MAX_EXPANDED_DAYS) {
        const isFirstDay = dayIndex === 0;
        // Reuse localDayKey's local-day logic (via the round-tripped ISO
        // string) so the day key/label generation never desyncs from the
        // rest of the compact-view grouping logic (e.g. collapseEntriesByActivityType).
        const dayKey = localDayKey(cursor.toISOString());

        pieces.push({
            ...entry,
            id: `${entry.id}:${dayKey}`,
            start: isFirstDay ? entry.start : cursor.toISOString(),
            end: null,
        });

        cursor.setDate(cursor.getDate() + 1);
        dayIndex += 1;
    }

    return pieces;
}

/**
 * Expand multi-day calendar entries into one synthetic, single-day marker
 * per covered local day.
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
 * included UNLESS `end` falls exactly at local midnight (00:00:00.000), in
 * which case that day is excluded (the event effectively finished at the
 * close of the previous day). Entries with `end === null`, or whose `start`
 * and `end` fall on the same local day, pass through unchanged.
 *
 * Each synthetic piece keeps every field of the original entry except:
 * - `id`: suffixed with the covered day (`${id}:${dayKey}`) so `entryUid`
 *   stays unique per day and doesn't collide with the original id or with
 *   other days of the same entry.
 * - `start`: the original start time on the first day; local midnight of
 *   the covered day (built with the same local-day logic as `localDayKey`)
 *   on every following day.
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
        const dayKey = localDayKey(entry.start);
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
