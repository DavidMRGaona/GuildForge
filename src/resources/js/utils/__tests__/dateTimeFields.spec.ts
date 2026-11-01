import { describe, expect, it } from 'vitest';
import {
    addMonths,
    dateFieldPattern,
    formatDateField,
    monthWeeks,
    parseDateField,
    parseTimeField,
    timeOptions,
} from '../dateTimeFields';

describe('dateFieldPattern', () => {
    it('follows the order and separator of the locale', () => {
        expect(dateFieldPattern('es')).toEqual({
            order: ['day', 'month', 'year'],
            separator: '/',
        });
        expect(dateFieldPattern('en-US')).toEqual({
            order: ['month', 'day', 'year'],
            separator: '/',
        });
    });
});

describe('formatDateField', () => {
    it('writes a day the way the locale reads it', () => {
        expect(formatDateField('2026-03-07', 'es')).toBe('07/03/2026');
        expect(formatDateField('2026-03-07', 'en-US')).toBe('03/07/2026');
    });

    it('leaves anything that is not a day alone', () => {
        expect(formatDateField('', 'es')).toBe('');
        expect(formatDateField('mañana', 'es')).toBe('mañana');
    });
});

describe('parseDateField', () => {
    it('reads a day in the order of the locale', () => {
        expect(parseDateField('7/3/2026', 'es')).toBe('2026-03-07');
        expect(parseDateField('3/7/2026', 'en-US')).toBe('2026-03-07');
    });

    it('accepts other separators, two-digit years and ISO days', () => {
        expect(parseDateField('07-03-26', 'es')).toBe('2026-03-07');
        expect(parseDateField('7.3.2026', 'es')).toBe('2026-03-07');
        expect(parseDateField(' 2026-03-07 ', 'es')).toBe('2026-03-07');
    });

    it('refuses days that do not exist', () => {
        expect(parseDateField('31/02/2026', 'es')).toBeNull();
        expect(parseDateField('29/02/2026', 'es')).toBeNull();
        expect(parseDateField('29/02/2028', 'es')).toBe('2028-02-29');
        expect(parseDateField('', 'es')).toBeNull();
        expect(parseDateField('mañana', 'es')).toBeNull();
        expect(parseDateField('1/1', 'es')).toBeNull();
    });
});

describe('parseTimeField', () => {
    it('reads the usual ways of writing a time', () => {
        expect(parseTimeField('18:30')).toBe('18:30');
        expect(parseTimeField('9:05')).toBe('09:05');
        expect(parseTimeField('9.30')).toBe('09:30');
        expect(parseTimeField('9h30')).toBe('09:30');
        expect(parseTimeField('9h')).toBe('09:00');
        expect(parseTimeField('9')).toBe('09:00');
        expect(parseTimeField('0930')).toBe('09:30');
        expect(parseTimeField('930')).toBe('09:30');
        expect(parseTimeField(' 23:59 ')).toBe('23:59');
    });

    it('refuses times outside the day', () => {
        expect(parseTimeField('24:00')).toBeNull();
        expect(parseTimeField('12:60')).toBeNull();
        expect(parseTimeField('')).toBeNull();
        expect(parseTimeField('tarde')).toBeNull();
    });
});

describe('timeOptions', () => {
    it('lists the day in steps of the given minutes', () => {
        const options = timeOptions(30);

        expect(options).toHaveLength(48);
        expect(options[0]).toBe('00:00');
        expect(options[1]).toBe('00:30');
        expect(options.at(-1)).toBe('23:30');
    });

    it('falls back to whole hours for a step that does not fit', () => {
        expect(timeOptions(0)).toHaveLength(24);
        expect(timeOptions(2000)).toHaveLength(24);
    });
});

describe('monthWeeks', () => {
    it('lays out a month in whole weeks starting on the given weekday', () => {
        const weeks = monthWeeks('2026-11', 1);

        expect(weeks[0]?.[0]).toBe('2026-10-26');
        expect(weeks[0]?.[6]).toBe('2026-11-01');
        expect(weeks.at(-1)?.[6]).toBe('2026-12-06');
        expect(weeks.every((week) => week.length === 7)).toBe(true);
    });

    it('starts on Sunday when asked', () => {
        expect(monthWeeks('2026-11', 0)[0]?.[0]).toBe('2026-11-01');
    });
});

describe('addMonths', () => {
    it('moves between months and years', () => {
        expect(addMonths('2026-12', 1)).toBe('2027-01');
        expect(addMonths('2026-01', -1)).toBe('2025-12');
    });
});
