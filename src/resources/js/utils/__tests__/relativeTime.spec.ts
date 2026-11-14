import { describe, expect, it } from 'vitest';
import { formatAbsoluteTime, formatRelativeTime } from '../relativeTime';

const NOW = new Date('2026-11-10T12:00:00Z');

describe('formatRelativeTime', () => {
    it('says «ahora» under a minute', () => {
        expect(formatRelativeTime('2026-11-10T11:59:30Z', 'es', NOW)).toBe('ahora');
    });

    it('counts minutes, hours and days back', () => {
        expect(formatRelativeTime('2026-11-10T11:55:00Z', 'es', NOW)).toBe('hace 5 minutos');
        expect(formatRelativeTime('2026-11-10T09:00:00Z', 'es', NOW)).toBe('hace 3 horas');
        expect(formatRelativeTime('2026-11-09T12:00:00Z', 'es', NOW)).toBe('ayer');
        expect(formatRelativeTime('2026-10-20T12:00:00Z', 'es', NOW)).toBe('hace 3 semanas');
    });

    it('never speaks of the future when the clock is a little ahead', () => {
        expect(formatRelativeTime('2026-11-10T12:00:20Z', 'es', NOW)).toBe('ahora');
    });

    it('gives nothing for a missing or unreadable instant', () => {
        expect(formatRelativeTime(null, 'es', NOW)).toBe('');
        expect(formatRelativeTime('ayer', 'es', NOW)).toBe('');
    });

    it('follows the locale', () => {
        expect(formatRelativeTime('2026-11-10T11:55:00Z', 'en', NOW)).toBe('5 minutes ago');
    });
});

describe('formatAbsoluteTime', () => {
    it('gives the date and time in the given zone', () => {
        expect(formatAbsoluteTime('2026-12-19T17:00:00Z', 'es', 'Europe/Madrid')).toBe(
            '19/12/2026, 18:00'
        );
        expect(formatAbsoluteTime('2026-12-19T18:00:00+01:00', 'es', 'UTC')).toBe(
            '19/12/2026, 17:00'
        );
    });

    it('gives nothing for a missing or unreadable instant', () => {
        expect(formatAbsoluteTime(null, 'es', 'Europe/Madrid')).toBe('');
        expect(formatAbsoluteTime('ayer', 'es', 'Europe/Madrid')).toBe('');
    });
});
