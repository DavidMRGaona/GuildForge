import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import KnowledgeFeedback from '../KnowledgeFeedback.vue';
import en from '@/locales/en';
import es from '@/locales/es';

function mountFeedback(): ReturnType<typeof mount> {
    return mount(KnowledgeFeedback, {
        props: { guideId: 'core.signup' },
        global: { plugins: [createI18n({ legacy: false, locale: 'es', messages: { es, en } })] },
    });
}

describe('KnowledgeFeedback', () => {
    const fetchMock = vi.fn();

    beforeEach(() => {
        fetchMock.mockReset();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('asks the question with a yes and a no button', () => {
        const wrapper = mountFeedback();

        expect(wrapper.text()).toContain('¿Te ha sido útil?');
        expect(wrapper.findAll('button')).toHaveLength(2);
    });

    it('posts the vote once and shows the thanks', async () => {
        fetchMock.mockResolvedValue({ ok: true, status: 204 });
        const wrapper = mountFeedback();

        await wrapper.findAll('button')[0]!.trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toBe('/ayuda/core.signup/voto');
        expect(init.method).toBe('POST');
        expect(JSON.parse(init.body as string)).toEqual({ helpful: true });
        expect(wrapper.text()).toContain('Gracias por tu opinión.');
        expect(wrapper.findAll('button')).toHaveLength(0);
    });

    it('sends a negative vote', async () => {
        fetchMock.mockResolvedValue({ ok: true, status: 204 });
        const wrapper = mountFeedback();

        await wrapper.findAll('button')[1]!.trigger('click');
        await flushPromises();

        const [, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(JSON.parse(init.body as string)).toEqual({ helpful: false });
    });

    it('fails silently and keeps the buttons when the request fails', async () => {
        fetchMock.mockRejectedValueOnce(new Error('offline'));
        const wrapper = mountFeedback();

        await wrapper.findAll('button')[0]!.trigger('click');
        await flushPromises();

        expect(wrapper.text()).not.toContain('Gracias');
        expect(wrapper.findAll('button')).toHaveLength(2);
        expect(wrapper.find('[role="alert"]').exists()).toBe(false);

        fetchMock.mockResolvedValueOnce({ ok: false, status: 429 });
        await wrapper.findAll('button')[0]!.trigger('click');
        await flushPromises();
        expect(wrapper.text()).not.toContain('Gracias');
    });
});
