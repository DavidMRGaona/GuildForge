import { describe, expect, it } from 'vitest';
import { normalizeForSearch, visibleRows, wantsSearch, type SelectOption } from '../selectOptions';

const options: SelectOption[] = [
    { value: 'a', label: 'General' },
    { value: 'b', label: 'Campañas' },
    { value: 'b1', label: 'Uno', level: 1 },
    { value: 'b2', label: 'Dos', level: 1 },
    { value: 'c', label: 'Mesas' },
    { value: 'd', label: 'Avisos' },
];

describe('selectOptions', () => {
    it('normalizes accents, case and spaces', () => {
        expect(normalizeForSearch('  Campañas   ÉPICAS ')).toBe('campanas epicas');
    });

    it('marks children, their parent and the dividers around the block', () => {
        const rows = visibleRows(options, '');

        expect(
            rows.map((row) => [row.option.value, row.level, row.hasChildren, row.dividerBefore])
        ).toEqual([
            ['a', 0, false, false],
            ['b', 0, true, true],
            ['b1', 1, false, false],
            ['b2', 1, false, false],
            ['c', 0, false, true],
            ['d', 0, false, false],
        ]);
        expect(rows[2]?.parentLabel).toBe('Campañas');
    });

    it('treats a child with nothing above it as top level', () => {
        const rows = visibleRows([{ value: 'x', label: 'Huérfana', level: 1 }], '');

        expect(rows[0]?.level).toBe(0);
        expect(rows[0]?.parentLabel).toBeNull();
    });

    it('keeps the parent of a matching child, and every word must match', () => {
        expect(visibleRows(options, 'dos').map((row) => row.option.value)).toEqual(['b', 'b2']);
        expect(visibleRows(options, 'campanas dos').map((row) => row.option.value)).toEqual([
            'b',
            'b2',
        ]);
        expect(visibleRows(options, 'mesas dos')).toEqual([]);
    });

    it('wants a search box from eight options unless told', () => {
        expect(wantsSearch(undefined, 7)).toBe(false);
        expect(wantsSearch(undefined, 8)).toBe(true);
        expect(wantsSearch(false, 20)).toBe(false);
        expect(wantsSearch(true, 2)).toBe(true);
    });
});
