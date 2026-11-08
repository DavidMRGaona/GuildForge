/** Views the site's calendars offer: the month grid and the month's list. */
export type CalendarView = 'month' | 'list';

const FULLCALENDAR_VIEWS: Record<CalendarView, string> = {
    month: 'dayGridMonth',
    list: 'listMonth',
};

/** FullCalendar's name for a calendar view. */
export function fullCalendarView(view: CalendarView): string {
    return FULLCALENDAR_VIEWS[view];
}

/**
 * The views to offer, in the given order and without repeats, always including
 * the initial one (first when it was missing).
 */
export function availableCalendarViews(
    initialView: CalendarView,
    views: readonly CalendarView[]
): CalendarView[] {
    const unique = [...new Set(views)];

    return unique.includes(initialView) ? unique : [initialView, ...unique];
}
