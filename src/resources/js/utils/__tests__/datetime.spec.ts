import { afterEach, describe, expect, it, vi } from 'vitest';

describe('VENUE_TIMEZONE', () => {
    afterEach(() => {
        document.head.innerHTML = '';
    });

    it('reads the display timezone from the meta tag', async () => {
        document.head.innerHTML = '<meta name="display-timezone" content="Atlantic/Canary">';
        vi.resetModules();
        const { VENUE_TIMEZONE } = await import('../datetime');
        expect(VENUE_TIMEZONE).toBe('Atlantic/Canary');
    });

    it('falls back to Europe/Madrid without the meta tag', async () => {
        document.head.innerHTML = '';
        vi.resetModules();
        const { VENUE_TIMEZONE } = await import('../datetime');
        expect(VENUE_TIMEZONE).toBe('Europe/Madrid');
    });

    it('falls back to Europe/Madrid when the meta value is not a timezone', async () => {
        document.head.innerHTML = '<meta name="display-timezone" content="Not/AZone">';
        vi.resetModules();
        const { VENUE_TIMEZONE } = await import('../datetime');
        expect(VENUE_TIMEZONE).toBe('Europe/Madrid');
    });
});

describe('addVenueDays', () => {
    afterEach(() => {
        document.head.innerHTML = '';
    });

    async function addVenueDaysIn(zone: string): Promise<(dayKey: string, days: number) => string> {
        document.head.innerHTML = `<meta name="display-timezone" content="${zone}">`;
        vi.resetModules();
        const { addVenueDays } = await import('../datetime');

        return addVenueDays;
    }

    // UTC+13 in the southern summer, UTC+14, UTC-12 and the default zone: the day moves
    // by exactly the days asked for whatever the offset (core#684)
    it.each(['Pacific/Auckland', 'Pacific/Kiritimati', 'Etc/GMT+12', 'Europe/Madrid'])(
        'moves a day by whole calendar days in %s',
        async (zone) => {
            const addVenueDays = await addVenueDaysIn(zone);

            expect(addVenueDays('2026-11-19', -1)).toBe('2026-11-18');
            expect(addVenueDays('2026-11-19', 0)).toBe('2026-11-19');
            expect(addVenueDays('2026-11-19', 1)).toBe('2026-11-20');
            expect(addVenueDays('2026-11-19', 7)).toBe('2026-11-26');
            expect(addVenueDays('2026-11-19', -30)).toBe('2026-10-20');
        }
    );

    it('crosses months, years and leap days', async () => {
        const addVenueDays = await addVenueDaysIn('Pacific/Kiritimati');

        expect(addVenueDays('2026-11-30', 1)).toBe('2026-12-01');
        expect(addVenueDays('2026-12-31', 1)).toBe('2027-01-01');
        expect(addVenueDays('2027-01-01', -1)).toBe('2026-12-31');
        expect(addVenueDays('2028-02-28', 1)).toBe('2028-02-29');
        expect(addVenueDays('2028-03-01', -1)).toBe('2028-02-29');
        expect(addVenueDays('2027-02-28', 1)).toBe('2027-03-01');
    });

    it('is not moved by the daylight-saving changes of the venue zone', async () => {
        const addVenueDays = await addVenueDaysIn('Europe/Madrid');

        // Clocks go forward on 29 March 2026 and back on 25 October 2026 in Madrid
        expect(addVenueDays('2026-03-28', 1)).toBe('2026-03-29');
        expect(addVenueDays('2026-03-29', 1)).toBe('2026-03-30');
        expect(addVenueDays('2026-10-25', -1)).toBe('2026-10-24');
        expect(addVenueDays('2026-10-24', 2)).toBe('2026-10-26');
    });
});

describe('venueDayStart', () => {
    afterEach(() => {
        document.head.innerHTML = '';
    });

    async function dayStartIn(zone: string, dayKey: string): Promise<string> {
        document.head.innerHTML = `<meta name="display-timezone" content="${zone}">`;
        vi.resetModules();
        const { venueDayStart } = await import('../datetime');

        return venueDayStart(dayKey).toISOString();
    }

    it.each([
        ['Etc/GMT+12', '2026-11-20', '2026-11-20T12:00:00.000Z'],
        ['America/Mexico_City', '2026-11-20', '2026-11-20T06:00:00.000Z'],
        ['Europe/Madrid', '2026-11-20', '2026-11-19T23:00:00.000Z'],
        ['Europe/Madrid', '2026-03-29', '2026-03-28T23:00:00.000Z'],
        ['Europe/Madrid', '2026-03-30', '2026-03-29T22:00:00.000Z'],
        ['Asia/Kathmandu', '2026-11-20', '2026-11-19T18:15:00.000Z'],
        ['Pacific/Kiritimati', '2026-11-20', '2026-11-19T10:00:00.000Z'],
        // Clocks jump from 00:00 to 01:00, so the day starts at 01:00
        ['America/Santiago', '2026-09-06', '2026-09-06T04:00:00.000Z'],
        ['America/Havana', '2026-03-08', '2026-03-08T05:00:00.000Z'],
    ])('returns the first instant of the venue day in %s on %s', async (zone, dayKey, expected) => {
        expect(await dayStartIn(zone, dayKey)).toBe(expected);
    });
});
