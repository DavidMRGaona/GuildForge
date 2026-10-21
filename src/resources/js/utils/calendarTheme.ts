import type { CalendarOptions } from '@fullcalendar/vue3';
import { joinClassNames } from '@fullcalendar/vue3';
import classicThemePlugin from '@fullcalendar/vue3/themes/classic';
import '@fullcalendar/vue3/skeleton.css';
import '@fullcalendar/vue3/themes/classic/theme.css';
import { venueDayKey } from '@/utils/datetime';

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
    // A multi-day event shows no time: its range ("06:03 – 06:03") reads as one day
    eventClass: (info) =>
        joinClassNames(
            'calendar-event',
            spansSeveralDays(info.event.start, info.event.end) && 'calendar-event--multiday'
        ),
    blockEventClass: 'calendar-event--block',
    columnEventClass: 'calendar-event--column',
    eventInnerClass: 'calendar-event-inner',
    eventTitleClass: 'calendar-event-title',
    eventTimeClass: 'calendar-event-time',
    listItemEventBeforeClass: 'calendar-event-dot',
    moreLinkClass: 'calendar-more-link',
    slotHeaderClass: 'calendar-slot-header',
};
