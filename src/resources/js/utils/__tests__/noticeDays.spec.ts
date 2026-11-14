import { describe, expect, it } from 'vitest';
import { groupByNoticeDay, noticeDayOf } from '../noticeDays';

// Thursday 19 November 2026, 10:00 in Madrid (the default venue zone in tests)
const NOW = new Date('2026-11-19T09:00:00Z');

describe('noticeDayOf', () => {
    it('sorts an instant into today, yesterday, this week or earlier in the venue zone', () => {
        expect(noticeDayOf('2026-11-19T00:30:00+01:00', NOW)).toBe('today');
        // 23:30 UTC on the 18th is already the 19th in Madrid
        expect(noticeDayOf('2026-11-18T23:30:00Z', NOW)).toBe('today');
        expect(noticeDayOf('2026-11-18T12:00:00+01:00', NOW)).toBe('yesterday');
        expect(noticeDayOf('2026-11-16T08:00:00+01:00', NOW)).toBe('thisWeek');
        expect(noticeDayOf('2026-11-15T22:00:00+01:00', NOW)).toBe('earlier');
    });

    it('calls the day before Monday «yesterday», not this week', () => {
        const monday = new Date('2026-11-16T09:00:00Z');

        expect(noticeDayOf('2026-11-15T12:00:00+01:00', monday)).toBe('yesterday');
        expect(noticeDayOf('2026-11-14T12:00:00+01:00', monday)).toBe('earlier');
    });

    it('puts a missing or unreadable instant with the earlier ones', () => {
        expect(noticeDayOf(null, NOW)).toBe('earlier');
        expect(noticeDayOf('ayer', NOW)).toBe('earlier');
    });
});

describe('groupByNoticeDay', () => {
    it('keeps the order and the position of every notice in the page', () => {
        const items = [
            { id: 'a', notifiedAt: '2026-11-19T08:00:00+01:00' },
            { id: 'b', notifiedAt: '2026-11-19T07:00:00+01:00' },
            { id: 'c', notifiedAt: '2026-11-17T07:00:00+01:00' },
            { id: 'd', notifiedAt: '2026-10-01T07:00:00+02:00' },
        ];

        const groups = groupByNoticeDay(items, NOW);

        expect(groups.map((group) => group.day)).toEqual(['today', 'thisWeek', 'earlier']);
        expect(groups[0]?.entries.map((entry) => [entry.item.id, entry.index])).toEqual([
            ['a', 0],
            ['b', 1],
        ]);
        expect(groups[2]?.entries[0]?.index).toBe(3);
    });

    it('gives nothing for an empty page', () => {
        expect(groupByNoticeDay([], NOW)).toEqual([]);
    });
});
