import { afterEach, describe, expect, it, vi } from 'vitest';
import type { CalendarEntry } from '@/types/models';

function entry(start: string, end: string | null): CalendarEntry {
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
    };
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
});
