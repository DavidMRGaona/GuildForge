import type { CalendarOptions } from '@fullcalendar/vue3';
import { joinClassNames } from '@fullcalendar/vue3';
import classicThemePlugin from '@fullcalendar/vue3/themes/classic';
import '@fullcalendar/vue3/skeleton.css';
import '@fullcalendar/vue3/themes/classic/theme.css';
import { VENUE_TIMEZONE, venueDayKey } from '@/utils/datetime';

/**
 * Look shared by every FullCalendar on the site, the core calendars and the
 * module ones (venue-bookings imports this file through `@/`).
 *
 * FullCalendar 7 ships no CSS of its own and no stable `fc-*` classes: the
 * theme's classes are hashed. The classic theme supplies the layout, and its
 * colours come from `--fc-classic-*` variables that resources/css/app.css maps
 * onto the site tokens under `.event-calendar`, so the colours chosen in the
 * site settings (and dark mode) reach the calendar. Its palette stylesheet is
 * deliberately not imported.
 */
export const calendarThemePlugin = classicThemePlugin;

/**
 * Whether an event starts and ends on different venue days. An end exactly at
 * midnight closes the previous day, so it does not count as another day.
 */
function spansSeveralDays(start: Date | null, end: Date | null): boolean {
    if (start === null || end === null) {
        return false;
    }

    return venueDayKey(start) !== venueDayKey(new Date(end.getTime() - 1));
}

const venueClock = new Intl.DateTimeFormat('en-GB', {
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
    timeZone: VENUE_TIMEZONE,
});

/**
 * Whether an event's time range would mislead: across several days, a start
 * and an end at the same hour ("06:03 – 06:03") read as an empty slot of one
 * day. Any other multi-day event shows its hours ("20:00 – 01:00").
 */
function hasMisleadingTimeRange(start: Date | null, end: Date | null): boolean {
    if (start === null || end === null || !spansSeveralDays(start, end)) {
        return false;
    }

    return venueClock.format(start) === venueClock.format(end);
}

/**
 * Time range of a timed event in the venue's time zone ("18:00–21:00"), or an
 * empty string for all-day events and for those whose range would mislead.
 *
 * Used for accessible names: FullCalendar drops the visible time in day cells
 * narrower than 60 px (phones), and an `aria-label` replaces the visible text.
 */
export function eventTimeRange(
    event: { allDay: boolean; start: Date | null; end: Date | null },
    locale: string
): string {
    if (event.allDay || event.start === null || hasMisleadingTimeRange(event.start, event.end)) {
        return '';
    }

    const formatter = new Intl.DateTimeFormat(locale, {
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
        timeZone: VENUE_TIMEZONE,
    });

    if (event.end === null) {
        return formatter.format(event.start);
    }

    // Across midnight formatRange() adds both dates ("22/10/2026, 20:00 – 23/10/2026, 1:00")
    return spansSeveralDays(event.start, event.end)
        ? `${formatter.format(event.start)}–${formatter.format(event.end)}`
        : formatter.formatRange(event.start, event.end);
}

/**
 * Stable class names for the rest of the styling in app.css. FullCalendar
 * joins them to the theme's own classes instead of replacing them.
 */
export const calendarClassOptions: CalendarOptions = {
    toolbarTitleClass: 'calendar-title',
    buttonClass: (info) =>
        joinClassNames('calendar-button', info.isSelected && 'calendar-button--selected'),
    tableClass: 'calendar-table',
    // The row carries the header colours: FullCalendar skips dayHeaderClass on disabled days
    dayHeaderRowClass: 'calendar-day-header-row',
    dayHeaderClass: 'calendar-day-header',
    dayHeaderDividerClass: 'calendar-header-divider',
    dayRowClass: 'calendar-row',
    dayCellClass: (info) =>
        joinClassNames(
            'calendar-day',
            info.isToday && 'calendar-day--today',
            info.isOther && 'calendar-day--other',
            info.isDisabled && 'calendar-day--disabled'
        ),
    dayCellTopClass: 'calendar-day-top',
    dayCellTopInnerClass: 'calendar-day-number',
    dayCellInnerClass: 'calendar-day-body',
    // A multi-day event shows its hours only on the piece where it starts: elsewhere
    // FullCalendar puts the whole range on a later week ("00:03 – 20:00" on its last day)
    eventClass: (info) => {
        const multiday = spansSeveralDays(info.event.start, info.event.end);
        const misleadingTime =
            hasMisleadingTimeRange(info.event.start, info.event.end) || (multiday && !info.isStart);

        return joinClassNames(
            'calendar-event',
            multiday && 'calendar-event--multiday',
            misleadingTime && 'calendar-event--no-time'
        );
    },
    blockEventClass: 'calendar-event--block',
    columnEventClass: 'calendar-event--column',
    eventInnerClass: 'calendar-event-inner',
    eventTitleClass: 'calendar-event-title',
    eventTimeClass: 'calendar-event-time',
    listItemEventBeforeClass: 'calendar-event-dot',
    moreLinkClass: 'calendar-more-link',
    slotHeaderClass: 'calendar-slot-header',
};
