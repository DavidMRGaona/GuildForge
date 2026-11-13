import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProposalStatusBadge from '../ProposalStatusBadge.vue';

const i18n = createI18n({
    legacy: false,
    locale: 'es',
    messages: {
        es: { proposals: { status: { draft: 'Borrador', pending_review: 'En revisión' } } },
    },
});

describe('ProposalStatusBadge', () => {
    it('says the status in words', () => {
        const wrapper = mount(ProposalStatusBadge, {
            props: { status: 'pending_review' },
            global: { plugins: [i18n] },
        });

        expect(wrapper.text()).toBe('En revisión');
    });
});
