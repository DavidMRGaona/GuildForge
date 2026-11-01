import { beforeEach, afterEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { computed, type ComputedRef } from 'vue';
import { createI18n } from 'vue-i18n';
import HelpLink from '../HelpLink.vue';
import { resetKnowledgeVisibility } from '@/composables/useKnowledgeVisibility';
import en from '@/locales/en';
import es from '@/locales/es';

const auth = { isAuthenticated: true };

vi.mock('@/composables/useAuth', () => ({
    useAuth: (): { isAuthenticated: ComputedRef<boolean> } => ({
        isAuthenticated: computed(() => auth.isAuthenticated),
    }),
}));

function mountLink(guide: string, label?: string): ReturnType<typeof mount> {
    return mount(HelpLink, {
        props: label === undefined ? { guide } : { guide, label },
        global: { plugins: [createI18n({ legacy: false, locale: 'es', messages: { es, en } })] },
    });
}

describe('HelpLink', () => {
    const fetchMock = vi.fn();

    beforeEach(() => {
        auth.isAuthenticated = true;
        resetKnowledgeVisibility();
        fetchMock.mockReset();
        fetchMock.mockResolvedValue({
            ok: true,
            json: () => Promise.resolve({ ids: ['core.signup'] }),
        });
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('fetches visible ids once for many links', async () => {
        const a = mountLink('core.signup');
        const b = mountLink('core.signup');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/ayuda/visibles');
        expect((init.headers as Record<string, string>).Accept).toBe('application/json');
        expect(a.find('a').attributes('href')).toBe('/ayuda/core.signup');
        expect(b.find('a').exists()).toBe(true);
    });

    it('renders nothing for an invisible guide', async () => {
        const wrapper = mountLink('core.other');
        await flushPromises();

        expect(wrapper.find('a').exists()).toBe(false);
    });

    it('renders nothing for guests and does not fetch', async () => {
        auth.isAuthenticated = false;
        const wrapper = mountLink('core.signup');
        await flushPromises();

        expect(fetchMock).not.toHaveBeenCalled();
        expect(wrapper.find('a').exists()).toBe(false);
    });

    it('treats a failed request as no visible guides', async () => {
        fetchMock.mockRejectedValue(new Error('offline'));
        const wrapper = mountLink('core.signup');
        await flushPromises();

        expect(wrapper.find('a').exists()).toBe(false);
    });

    it('uses an accessible label', async () => {
        const wrapper = mountLink('core.signup', 'Cómo apuntarse');
        await flushPromises();

        expect(wrapper.find('a').attributes('aria-label')).toBe('Cómo apuntarse');
    });
});
