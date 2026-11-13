import { describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileProposalsTab from '../ProfileProposalsTab.vue';
import type { ProfileProposals, Proposal } from '@/types/proposals';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    router: { post: vi.fn(), delete: vi.fn() },
}));

const i18n = createI18n({
    legacy: false,
    locale: 'es',
    missingWarn: false,
    fallbackWarn: false,
    messages: { es: {} },
});

function proposal(overrides: Partial<Proposal>): Proposal {
    return {
        id: 'a',
        type: 'event',
        status: 'draft',
        title: 'Jornada',
        body: null,
        excerpt: null,
        location: null,
        startDate: null,
        startTime: null,
        endDate: null,
        endTime: null,
        startsAt: null,
        imagePublicId: null,
        tagIds: [],
        note: null,
        reason: null,
        submittedAt: null,
        reviewedAt: null,
        updatedAt: '2026-11-13T10:00:00+01:00',
        publicPath: null,
        can: { edit: true, submit: true, withdraw: false, delete: true },
        ...overrides,
    };
}

function mountTab(items: Proposal[], canPropose = { event: true, article: false }): VueWrapper {
    const proposals: ProfileProposals = {
        available: true,
        canPropose,
        items,
        limits: {
            pendingUsed: 1,
            pendingMax: 3,
            draftsUsed: 2,
            draftsMax: 5,
            submissionsLeft: 4,
            submissionsPerHour: 5,
        },
    };

    return mount(ProfileProposalsTab, { props: { proposals }, global: { plugins: [i18n] } });
}

describe('ProfileProposalsTab', () => {
    it('shows the empty message and the limits', () => {
        const wrapper = mountTab([]);

        expect(wrapper.find('[data-testid="no-proposals"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="proposal-limits"]').text()).toContain('1 / 3');
        expect(wrapper.find('[data-testid="proposal-limits"]').text()).toContain('2 / 5');
    });

    it('offers only the buttons to propose what is allowed', () => {
        const wrapper = mountTab([]);

        expect(wrapper.find('[data-testid="propose-event"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="propose-article"]').exists()).toBe(false);
    });

    it('offers to the author only what the server allows', () => {
        const wrapper = mountTab([
            proposal({
                id: 'p',
                status: 'pending_review',
                can: { edit: false, submit: false, withdraw: true, delete: false },
            }),
        ]);

        expect(wrapper.find('[data-testid="withdraw-proposal"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="edit-proposal"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="delete-proposal"]').exists()).toBe(false);
    });

    it('shows the reason of a rejection', () => {
        const wrapper = mountTab([
            proposal({
                status: 'rejected',
                reason: 'No encaja',
                can: { edit: false, submit: false, withdraw: false, delete: true },
            }),
        ]);

        expect(wrapper.find('[data-testid="proposal-reason"]').text()).toContain('No encaja');
    });
});
