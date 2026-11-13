import { describe, expect, it } from 'vitest';
import { initialRequests, isFilled, visibleFields } from '../proposalRequests';
import type { ProposalRequestDefinition, ProposalRequestField } from '@/types/proposals';

function field(
    overrides: Partial<ProposalRequestField> & Pick<ProposalRequestField, 'name' | 'type'>
): ProposalRequestField {
    return {
        label: overrides.name,
        required: false,
        helperText: null,
        min: null,
        max: null,
        maxLength: null,
        options: [],
        default: null,
        visibleWhen: null,
        ...overrides,
    };
}

const fields: ProposalRequestField[] = [
    field({ name: 'capacity', type: 'number', min: 1 }),
    field({ name: 'waitlist', type: 'boolean', visibleWhen: { field: 'capacity', values: null } }),
    field({
        name: 'mode',
        type: 'select',
        default: 'open',
        options: [{ value: 'open', label: 'Abiertas' }],
    }),
    field({ name: 'note', type: 'text', visibleWhen: { field: 'mode', values: ['members'] } }),
    field({ name: 'reason', type: 'text', visibleWhen: { field: 'note', values: null } }),
];

describe('proposal requests', () => {
    it('treats nothing, no, zero and empty text as not filled', () => {
        expect([null, false, 0, ''].map(isFilled)).toEqual([false, false, false, false]);
        expect([true, 1, 'x'].map(isFilled)).toEqual([true, true, true]);
    });

    it('shows a dependent field only while its field holds what it waits for', () => {
        expect(visibleFields(fields, { capacity: '', mode: 'open' })).toEqual(['capacity', 'mode']);
        expect(visibleFields(fields, { capacity: '0', mode: 'open' })).toEqual([
            'capacity',
            'mode',
        ]);
        expect(visibleFields(fields, { capacity: '12', mode: 'members', note: 'x' })).toEqual([
            'capacity',
            'waitlist',
            'mode',
            'note',
            'reason',
        ]);
    });

    it('hides what depends on a hidden field, whatever it holds', () => {
        expect(visibleFields(fields, { mode: 'open', note: 'left over' })).not.toContain('reason');
    });

    it('starts from the stored values, then the defaults', () => {
        const definitions: ProposalRequestDefinition[] = [
            { key: 'fixture.one', label: 'Uno', question: '¿Uno?', fields },
            { key: 'fixture.two', label: 'Dos', question: '¿Dos?', fields: [] },
        ];

        const state = initialRequests(definitions, [
            {
                key: 'fixture.one',
                label: 'Uno',
                active: true,
                values: { capacity: 20, waitlist: true },
                description: null,
                warnings: [],
                applied: false,
            },
        ]);

        expect(state['fixture.one']).toEqual({
            wanted: true,
            values: { capacity: '20', waitlist: true, mode: 'open', note: '', reason: '' },
        });
        expect(state['fixture.two']).toEqual({ wanted: false, values: {} });
    });
});
