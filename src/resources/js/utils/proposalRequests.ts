import type {
    OwnProposalRequest,
    ProposalRequestDefinition,
    ProposalRequestField,
    ProposalRequestState,
    ProposalRequestValue,
} from '@/types/proposals';

/**
 * The browser side of the module requests of event proposals (core#581): which fields show and
 * what the form starts with. It mirrors the server's rule (ProposalRequestValueRule), which checks
 * everything again: this only decides what to draw.
 */

/** Anything but nothing, no, zero or an empty text. */
export function isFilled(value: ProposalRequestValue): boolean {
    return value !== null && value !== false && value !== 0 && value !== '';
}

/** A number input holds text: compare it as the server will read it. */
function comparable(
    field: ProposalRequestField,
    value: ProposalRequestValue
): ProposalRequestValue {
    if (field.type === 'number' && typeof value === 'string') {
        return /^-?\d+$/.test(value.trim()) ? Number(value.trim()) : value === '' ? null : value;
    }

    return value === '' ? null : value;
}

/** Names of the fields that show with these values, in order. */
export function visibleFields(
    fields: ProposalRequestField[],
    values: Record<string, ProposalRequestValue>
): string[] {
    const seen: Record<string, ProposalRequestValue> = {};
    const visible: string[] = [];

    for (const field of fields) {
        const condition = field.visibleWhen;
        const controlling = condition === null ? null : (seen[condition.field] ?? null);
        const shows =
            condition === null ||
            (condition.values === null
                ? isFilled(controlling)
                : condition.values.some((expected) => expected === controlling));

        seen[field.name] = shows ? comparable(field, values[field.name] ?? null) : null;

        if (shows) {
            visible.push(field.name);
        }
    }

    return visible;
}

/** What a field starts with: its stored value, else its default (no for a yes/no). */
function initialValue(
    field: ProposalRequestField,
    stored: ProposalRequestValue | undefined
): ProposalRequestValue {
    if (stored !== undefined && stored !== null) {
        return field.type === 'number' ? String(stored) : stored;
    }

    if (field.type === 'boolean') {
        return field.default === true;
    }

    if (field.default === null) {
        return '';
    }

    return field.type === 'number' ? String(field.default) : field.default;
}

/** The form state of every offered request, ticked when the proposal already asks for it. */
export function initialRequests(
    definitions: ProposalRequestDefinition[],
    stored: OwnProposalRequest[]
): Record<string, ProposalRequestState> {
    const state: Record<string, ProposalRequestState> = {};

    for (const definition of definitions) {
        const existing = stored.find((request) => request.key === definition.key);
        const values: Record<string, ProposalRequestValue> = {};

        for (const field of definition.fields) {
            values[field.name] = initialValue(field, existing?.values[field.name]);
        }

        state[definition.key] = { wanted: existing !== undefined, values };
    }

    return state;
}
