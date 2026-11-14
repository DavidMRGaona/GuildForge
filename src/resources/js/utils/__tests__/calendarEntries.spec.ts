import { afterEach, describe, expect, it, vi } from 'vitest';
import type { CalendarEntry } from '@/types/models';

function entry(
    start: string,
    end: string | null,
    overrides: Partial<CalendarEntry> = {}
): CalendarEntry {
    return {
        id: 'e1',
        sourceType: 'event',
        sourceLabel: 'Eventos',
        color: 'primary',
        title: 'Jornadas',
        description: '',
        start,
        end,
        location: null,
        imagePublicId: null,
        url: '/eventos/jornadas',
        tags: [],
        ...overrides,
    };
}

type CalendarEntriesModule = typeof import('../calendarEntries');

async function loadIn(zone: string): Promise<CalendarEntriesModule> {
    document.head.innerHTML = `<meta name="display-timezone" content="${zone}">`;
    vi.resetModules();

    return import('../calendarEntries');
}

function previousDayKey(dayKey: string): string {
    return new Date(Date.parse(`${dayKey}T00:00:00Z`) - 86_400_000).toISOString().slice(0, 10);
}

describe('expandEntriesPerDay', () => {
    afterEach(() => {
        document.head.innerHTML = '';
    });

    async function expandIn(zone: string, item: CalendarEntry): Promise<string[]> {
        document.head.innerHTML = `<meta name="display-timezone" content="${zone}">`;
        vi.resetModules();
        const { expandEntriesPerDay } = await import('../calendarEntries');

        return expandEntriesPerDay([item]).map((piece) => piece.id);
    }

    it('gives a multi-day entry one piece per venue day', async () => {
        const ids = await expandIn(
            'Europe/Madrid',
            entry('2026-11-19T10:00:00+01:00', '2026-11-21T18:00:00+01:00')
        );

        expect(ids).toEqual(['e1:2026-11-19', 'e1:2026-11-20', 'e1:2026-11-21']);
    });

    it('skips no day in a zone ahead of UTC+12 (core#684)', async () => {
        const ids = await expandIn(
            'Pacific/Auckland',
            entry('2026-11-19T10:00:00+13:00', '2026-11-21T18:00:00+13:00')
        );

        expect(ids).toEqual(['e1:2026-11-19', 'e1:2026-11-20', 'e1:2026-11-21']);
    });

    it('leaves out the day an entry ends at venue midnight', async () => {
        const ids = await expandIn(
            'Pacific/Auckland',
            entry('2026-11-19T10:00:00+13:00', '2026-11-21T00:00:00+13:00')
        );

        expect(ids).toEqual(['e1:2026-11-19', 'e1:2026-11-20']);
    });

    // Offsets of 19-21 Nov 2026 in each zone (none changes its clock then)
    it.each([
        ['America/Mexico_City', '-06:00'],
        ['Etc/GMT+12', '-12:00'],
        ['Europe/Madrid', '+01:00'],
        ['Pacific/Auckland', '+13:00'],
        ['Pacific/Kiritimati', '+14:00'],
    ])('puts every piece on its own venue day in %s (core#690)', async (zone, offset) => {
        const { expandEntriesPerDay, entryDayKey, toCompactDayEvents } = await loadIn(zone);
        const pieces = expandEntriesPerDay([
            entry(`2026-11-19T10:00:00${offset}`, `2026-11-21T18:00:00${offset}`),
        ]);

        expect(pieces.map((piece) => piece.id)).toEqual([
            'e1:2026-11-19',
            'e1:2026-11-20',
            'e1:2026-11-21',
        ]);
        expect(pieces.map((piece) => entryDayKey(piece.start))).toEqual([
            '2026-11-19',
            '2026-11-20',
            '2026-11-21',
        ]);
        expect(toCompactDayEvents(pieces).map((day) => day.id)).toEqual([
            'day:2026-11-19',
            'day:2026-11-20',
            'day:2026-11-21',
        ]);
    });

    it.each([
        ['America/Mexico_City', '-06:00'],
        ['Etc/GMT+12', '-12:00'],
        ['Europe/Madrid', '+01:00'],
        ['Pacific/Kiritimati', '+14:00'],
    ])(
        'anchors the following days at the first instant of the venue day in %s',
        async (zone, offset) => {
            const { expandEntriesPerDay, entryDayKey } = await loadIn(zone);
            const [, ...following] = expandEntriesPerDay([
                entry(`2026-11-19T10:00:00${offset}`, `2026-11-21T18:00:00${offset}`),
            ]);

            for (const piece of following) {
                const dayKey = piece.id.slice('e1:'.length);
                const instant = Date.parse(piece.start);

                expect(entryDayKey(piece.start)).toBe(dayKey);
                expect(entryDayKey(new Date(instant - 1).toISOString())).toBe(
                    previousDayKey(dayKey)
                );
            }
        }
    );

    // Both zones jump from 00:00 straight to 01:00 that day, so its first instant is 01:00
    it.each([
        [
            'America/Santiago',
            '2026-09-05T10:00:00-04:00',
            '2026-09-07T18:00:00-03:00',
            '2026-09-06T04:00:00.000Z',
        ],
        [
            'America/Havana',
            '2026-03-07T10:00:00-05:00',
            '2026-03-09T18:00:00-04:00',
            '2026-03-08T05:00:00.000Z',
        ],
    ])(
        'anchors a day without a local midnight at its first instant in %s',
        async (zone, start, end, anchor) => {
            const { expandEntriesPerDay, entryDayKey } = await loadIn(zone);
            const pieces = expandEntriesPerDay([entry(start, end)]);
            const middle = pieces[1];

            expect(pieces.map((piece) => entryDayKey(piece.start))).toEqual(
                pieces.map((piece) => piece.id.slice('e1:'.length))
            );
            expect(middle).toBeDefined();
            expect(new Date(middle?.start ?? '').toISOString()).toBe(anchor);
        }
    );
});

describe('first instant of a following venue day', () => {
    afterEach(() => {
        document.head.innerHTML = '';
    });

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
    ])('anchors the piece in %s on %s at %s', async (zone, dayKey, expected) => {
        const { expandEntriesPerDay } = await loadIn(zone);
        // From the day before to the day after in every zone from UTC-12 to UTC+14
        const day = Date.parse(`${dayKey}T00:00:00Z`);
        const start = new Date(day - 18 * 3_600_000).toISOString();
        const end = new Date(day + 42 * 3_600_000).toISOString();

        const piece = expandEntriesPerDay([entry(start, end)]).find(
            (item) => item.id === `e1:${dayKey}`
        );

        expect(piece?.start).toBe(expected);
    });
});

describe('collapseEntriesByActivityType with multi-day pieces', () => {
    afterEach(() => {
        document.head.innerHTML = '';
    });

    it.each([
        ['America/Mexico_City', '-06:00'],
        ['Etc/GMT+12', '-12:00'],
        ['Europe/Madrid', '+01:00'],
        ['Pacific/Kiritimati', '+14:00'],
    ])(
        'keeps a following-day piece ahead of the timed entries of that day in %s',
        async (zone, offset) => {
            const { collapseEntriesByActivityType, expandEntriesPerDay, toCompactDayEvents } =
                await loadIn(zone);
            // In start order, as the server sends them
            const entries = [
                entry(`2026-11-19T10:00:00${offset}`, `2026-11-21T18:00:00${offset}`),
                entry(`2026-11-20T00:00:00${offset}`, null, { id: 'midnight' }),
                entry(`2026-11-20T09:00:00${offset}`, null, { id: 'morning' }),
                entry(`2026-11-20T09:00:00${offset}`, null, { id: 't1', sourceType: 'game_table' }),
            ];

            const days = toCompactDayEvents(
                collapseEntriesByActivityType(expandEntriesPerDay(entries))
            );

            expect(days.find((day) => day.id === 'day:2026-11-20')?.extendedProps).toEqual({
                entryUids: ['event:e1:2026-11-20', 'game_table:t1'],
            });
        }
    );
});
