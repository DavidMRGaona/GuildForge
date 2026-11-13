import { describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProposalModuleRequests from '../ProposalModuleRequests.vue';
import type { ProposalRequestDefinition, ProposalRequestState } from '@/types/proposals';

const i18n = createI18n({
    legacy: false,
    locale: 'es',
    missingWarn: false,
    fallbackWarn: false,
    messages: { es: {} },
});

const definition: ProposalRequestDefinition = {
    key: 'fixture.registrations',
    label: 'Inscripciones',
    question: '¿Quieres inscripciones?',
    fields: [
        {
            name: 'capacity',
            type: 'number',
            label: 'Aforo',
            required: false,
            helperText: null,
            min: 1,
            max: 500,
            maxLength: null,
            options: [],
            default: null,
            visibleWhen: null,
        },
        {
            name: 'waitlist',
            type: 'boolean',
            label: 'Lista de espera',
            required: false,
            helperText: null,
            min: null,
            max: null,
            maxLength: null,
            options: [],
            default: false,
            visibleWhen: { field: 'capacity', values: null },
        },
    ],
};

function mountRequests(
    state: ProposalRequestState,
    errors: Record<string, string | undefined> = {}
): VueWrapper {
    return mount(ProposalModuleRequests, {
        props: {
            definitions: [definition],
            errors,
            modelValue: { [definition.key]: state },
        },
        global: { plugins: [i18n] },
    });
}

describe('ProposalModuleRequests', () => {
    it('draws nothing when no module offers a request', () => {
        const wrapper = mount(ProposalModuleRequests, {
            props: { definitions: [], errors: {}, modelValue: {} },
            global: { plugins: [i18n] },
        });

        expect(wrapper.find('[data-testid="proposal-requests"]').exists()).toBe(false);
    });

    it('shows the fields only once the question is ticked', async () => {
        const wrapper = mountRequests({ wanted: false, values: { capacity: '', waitlist: false } });

        expect(wrapper.text()).toContain('¿Quieres inscripciones?');
        expect(wrapper.find('#proposal-request-fixture-registrations-capacity').exists()).toBe(
            false
        );

        await wrapper.find('[data-testid="proposal-request-wanted"]').setValue(true);

        const updates = wrapper.emitted('update:modelValue');
        expect(updates?.[0]?.[0]).toEqual({
            [definition.key]: { wanted: true, values: { capacity: '', waitlist: false } },
        });
    });

    it('shows a dependent field and the server errors of each field', () => {
        const wrapper = mountRequests(
            { wanted: true, values: { capacity: '12', waitlist: false } },
            { 'requests.fixture.registrations.capacity': '«Aforo» debe ser como máximo 500.' }
        );

        expect(wrapper.find('#proposal-request-fixture-registrations-capacity').exists()).toBe(
            true
        );
        expect(wrapper.find('#proposal-request-fixture-registrations-waitlist').exists()).toBe(
            true
        );
        expect(wrapper.text()).toContain('«Aforo» debe ser como máximo 500.');
    });

    it('hides the dependent field while what it waits for is empty', () => {
        const wrapper = mountRequests({ wanted: true, values: { capacity: '', waitlist: true } });

        expect(wrapper.find('#proposal-request-fixture-registrations-waitlist').exists()).toBe(
            false
        );
    });
});
