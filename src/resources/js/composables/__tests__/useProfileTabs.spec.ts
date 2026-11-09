import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, ref, type Ref } from 'vue';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { MAIL_TAB, useProfileTabs } from '../useProfileTabs';
import es from '@/locales/es';

interface VisitOptions {
    only?: string[];
    preserveState?: boolean;
    preserveScroll?: boolean;
    replace?: boolean;
    onCancelToken?: (token: { cancel: () => void }) => void;
    onSuccess?: () => void;
    onError?: () => void;
    onHttpException?: () => boolean | void;
    onNetworkError?: () => boolean | void;
    onFinish?: () => void;
}

const page = vi.hoisted(() => ({
    url: '/perfil',
    props: {} as Record<string, unknown>,
}));
const router = vi.hoisted(() => ({ replace: vi.fn(), visit: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router,
    usePage: (): typeof page => page,
}));

vi.mock('../useModuleSlots', () => ({
    useModuleSlots: (): { getSlotComponents: () => never[]; moduleSlots: Ref<object> } => ({
        getSlotComponents: (): never[] => [],
        moduleSlots: ref({}),
    }),
}));

function mountTabs(): ReturnType<typeof useProfileTabs> {
    let tabs: ReturnType<typeof useProfileTabs> | undefined;
    mount(
        defineComponent({
            setup(): () => null {
                tabs = useProfileTabs();

                return () => null;
            },
        }),
        {
            global: {
                plugins: [createI18n({ legacy: false, locale: 'es', messages: { es } })],
            },
        }
    );

    if (tabs === undefined) {
        throw new Error('useProfileTabs did not run');
    }

    return tabs;
}

function lastVisit(): [string, VisitOptions] {
    const call = router.visit.mock.lastCall as [string, VisitOptions] | undefined;
    if (call === undefined) {
        throw new Error('No visit was made');
    }

    return call;
}

describe('useProfileTabs', () => {
    beforeEach(() => {
        router.replace.mockReset();
        router.visit.mockReset();
        page.url = '/perfil';
        page.props = {};
    });

    it('asks the server for the mail preferences when the mail tab is opened without them', () => {
        const tabs = mountTabs();

        tabs.setActiveTab(MAIL_TAB);

        expect(tabs.activeTabId.value).toBe(MAIL_TAB);
        expect(router.replace).not.toHaveBeenCalled();
        const [url, options] = lastVisit();
        expect(url).toBe('/perfil?tab=mail');
        expect(options).toMatchObject({
            only: ['mailPreferences'],
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    });

    it('only changes the address when the mail preferences are already on the page', () => {
        page.props = { mailPreferences: { sections: [], pause: {}, mutedSubjects: [] } };
        const tabs = mountTabs();

        tabs.setActiveTab(MAIL_TAB);

        expect(router.visit).not.toHaveBeenCalled();
        expect(router.replace).toHaveBeenCalledWith(
            expect.objectContaining({ url: '/perfil?tab=mail' })
        );
    });

    it('keeps other tabs as a client-side change', () => {
        const tabs = mountTabs();

        tabs.setActiveTab('account');

        expect(router.visit).not.toHaveBeenCalled();
        expect(router.replace).toHaveBeenCalledWith(expect.objectContaining({ url: '/perfil' }));
    });

    it.each(['onHttpException', 'onNetworkError'] as const)(
        'reports a failed load on %s and keeps the error dialog away',
        (callback) => {
            const tabs = mountTabs();
            tabs.setActiveTab(MAIL_TAB);
            const [, options] = lastVisit();

            const handled = options[callback]?.();

            expect(handled).toBe(false);
            expect(tabs.mailPreferencesFailed.value).toBe(true);
        }
    );

    it('tries again from scratch when retrying', () => {
        const tabs = mountTabs();
        tabs.setActiveTab(MAIL_TAB);
        lastVisit()[1].onNetworkError?.();

        tabs.loadMailPreferences();

        expect(tabs.mailPreferencesFailed.value).toBe(false);
        expect(router.visit).toHaveBeenCalledTimes(2);
        expect(lastVisit()[1].only).toEqual(['mailPreferences']);
    });

    it('cancels a pending load when another tab is opened', () => {
        const cancel = vi.fn();
        const tabs = mountTabs();
        tabs.setActiveTab(MAIL_TAB);
        lastVisit()[1].onCancelToken?.({ cancel });

        tabs.setActiveTab('account');

        expect(cancel).toHaveBeenCalledOnce();
        expect(tabs.activeTabId.value).toBe('account');
        expect(router.replace).toHaveBeenCalledWith(expect.objectContaining({ url: '/perfil' }));
    });
});
