import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import KnowledgeSnippet from '../KnowledgeSnippet.vue';
import KnowledgeGuideBadge from '../KnowledgeGuideBadge.vue';
import KnowledgeSearchBox from '../KnowledgeSearchBox.vue';
import en from '@/locales/en';
import es from '@/locales/es';

const routerGet = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: { get: (...args: unknown[]): void => routerGet(...args) },
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

function i18n(): ReturnType<typeof createI18n> {
    return createI18n({ legacy: false, locale: 'es', messages: { es, en } });
}

describe('KnowledgeSnippet', () => {
    it('renders matches as mark elements without v-html', () => {
        const wrapper = mount(KnowledgeSnippet, {
            props: {
                segments: [
                    { text: 'Ins', match: true },
                    { text: 'cribirte <b>hoy</b>', match: false },
                ],
            },
        });

        expect(wrapper.findAll('mark')).toHaveLength(1);
        expect(wrapper.get('mark').text()).toBe('Ins');
        expect(wrapper.find('b').exists()).toBe(false);
        expect(wrapper.text()).toContain('<b>hoy</b>');
    });
});

describe('KnowledgeGuideBadge', () => {
    it('shows the new and updated badges with accessible text', () => {
        const fresh = mount(KnowledgeGuideBadge, {
            props: { badge: 'new' },
            global: { plugins: [i18n()] },
        });
        const updated = mount(KnowledgeGuideBadge, {
            props: { badge: 'updated' },
            global: { plugins: [i18n()] },
        });
        const none = mount(KnowledgeGuideBadge, {
            props: { badge: null },
            global: { plugins: [i18n()] },
        });

        expect(fresh.text()).toBe('Nueva');
        expect(updated.text()).toBe('Actualizada');
        expect(none.html()).toBe('<!--v-if-->');
    });
});

describe('KnowledgeSearchBox', () => {
    const fetchMock = vi.fn();

    beforeEach(() => {
        vi.useFakeTimers();
        routerGet.mockReset();
        fetchMock.mockReset();
        fetchMock.mockImplementation(
            (_url: string, init: { signal: AbortSignal }) =>
                new Promise((resolve) => {
                    init.signal.addEventListener('abort', () =>
                        resolve({ ok: false, json: () => ({}) })
                    );
                    resolve({
                        ok: true,
                        json: () =>
                            Promise.resolve({
                                results: [
                                    {
                                        id: 'game-tables.join',
                                        title: 'Apuntarte a una mesa',
                                        categoryId: 'events',
                                        snippet: [{ text: 'Apuntarte', match: true }],
                                    },
                                ],
                            }),
                    });
                })
        );
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.useRealTimers();
        vi.unstubAllGlobals();
    });

    function box(): ReturnType<typeof mount> {
        return mount(KnowledgeSearchBox, { global: { plugins: [i18n()] } });
    }

    it('debounces and aborts previous searches', async () => {
        const wrapper = box();
        const input = wrapper.get('input');

        await input.setValue('ins');
        vi.advanceTimersByTime(100);
        await input.setValue('insc');
        vi.advanceTimersByTime(250);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(1);
        const [url, init] = fetchMock.mock.calls[0] as [string, RequestInit];
        expect(url).toContain('/ayuda/buscar?q=insc');
        expect((init.headers as Record<string, string>).Accept).toBe('application/json');

        await input.setValue('inscr');
        vi.advanceTimersByTime(250);
        const first = (fetchMock.mock.calls[0] as [string, RequestInit])[1].signal as AbortSignal;
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledTimes(2);
        expect(first.aborted).toBe(true);
    });

    it('lists the results as a keyboard navigable listbox', async () => {
        const wrapper = box();
        const input = wrapper.get('input');

        await input.setValue('mesa');
        vi.advanceTimersByTime(250);
        await flushPromises();

        expect(input.attributes('role')).toBe('combobox');
        expect(input.attributes('aria-expanded')).toBe('true');
        expect(wrapper.get('[role="listbox"]').findAll('[role="option"]')).toHaveLength(1);

        await input.trigger('keydown', { key: 'ArrowDown' });
        expect(input.attributes('aria-activedescendant')).toBe(
            wrapper.get('[role="option"]').attributes('id')
        );
        await input.trigger('keydown', { key: 'Enter' });

        expect(routerGet).toHaveBeenCalledWith('/ayuda/game-tables.join');
    });

    it('stays silent when the search fails', async () => {
        fetchMock.mockRejectedValue(new Error('network'));
        const wrapper = box();

        await wrapper.get('input').setValue('mesa');
        vi.advanceTimersByTime(250);
        await flushPromises();

        expect(wrapper.find('[role="option"]').exists()).toBe(false);
        expect(wrapper.get('input').attributes('aria-expanded')).toBe('false');
    });

    it('navigates to the results page on enter', async () => {
        const wrapper = box();
        const input = wrapper.get('input');

        await input.setValue('cuota');
        await input.trigger('keydown', { key: 'Enter' });

        expect(routerGet).toHaveBeenCalledWith('/ayuda', { q: 'cuota' });
    });
});
