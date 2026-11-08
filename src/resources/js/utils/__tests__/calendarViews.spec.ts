import { describe, expect, it } from 'vitest';
import { availableCalendarViews, fullCalendarView } from '../calendarViews';

describe('calendarViews', () => {
    it('names the views as FullCalendar does', () => {
        expect(fullCalendarView('month')).toBe('dayGridMonth');
        expect(fullCalendarView('list')).toBe('listMonth');
    });

    it('keeps the given order without repeats', () => {
        expect(availableCalendarViews('month', ['list', 'month', 'list'])).toEqual([
            'list',
            'month',
        ]);
    });

    it('puts the initial view first when it is missing', () => {
        expect(availableCalendarViews('list', ['month'])).toEqual(['list', 'month']);
        expect(availableCalendarViews('month', [])).toEqual(['month']);
    });
});
