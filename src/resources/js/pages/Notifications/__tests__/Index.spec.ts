import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import Index from '../Index.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { SiteNotificationItem } from '@/types/siteNotifications';

interface VisitOptions {
    preserveScroll?: boolean;
    onSuccess?: () => void;
    onError?: () => void;
    onFinish?: () => void;
}

const post = vi.fn<(url: string, data: object, options: VisitOptions) => void>();
const visit = vi.fn<(url: string) => void>();

vi.mock('@inertiajs/vue3', () => ({
    router: {
        post: (url: string, data: object, options: VisitOptions): void => post(url, data, options),
        get: vi.fn(),
        visit: (url: string): void => visit(url),
    },
    usePage: (): { url: string; props: Record<string, unknown> } => ({ url: '/avisos', props: {} }),
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

function item(id: string, overrides: Partial<SiteNotificationItem> = {}): SiteNotificationItem {
    return {
        id,
        title: `Aviso ${id}`,
        body: null,
        icon: null,
        count: 1,
        unread: true,
        notifiedAt: new Date().toISOString(),
        href: `/avisos/${id}`,
        ...overrides,
    };
}

function page(items: SiteNotificationItem[], prev: string | null = null): object {
    return {
        data: items,
        meta: { currentPage: prev === null ? 1 : 2, lastPage: 2, perPage: 20, total: items.length },
        links: { first: null, last: null, prev, next: null },
    };
}

function mountPage(items: SiteNotificationItem[], filter: 'all' | 'unread' = 'all'): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(Index, {
        props: {
            unread: items.filter((entry) => entry.unread).length,
            filter,
            notifications: {
                data: items,
                meta: { currentPage: 1, lastPage: 1, perPage: 20, total: items.length },
                links: { first: null, last: null, prev: null, next: null },
            },
        },
        global: { plugins: [i18n] },
        attachTo: document.body,
    });
}

describe('Notifications/Index', () => {
    beforeEach(() => {
        post.mockReset();
        visit.mockReset();
    });

    it('goes back a page when hiding empties a later one', async () => {
        const wrapper = mountPage([item('a')]);
        await wrapper.setProps({ notifications: page([item('a')], '/avisos?page=1') });
        post.mockImplementation((_url, _data, options) => {
            void wrapper.setProps({ notifications: page([], '/avisos?page=1') }).then(() => {
                options.onSuccess?.();
                options.onFinish?.();
            });
        });

        await wrapper.get('[data-testid="site-notification-menu"]').trigger('click');
        await wrapper.get('[data-testid="site-notification-hide"]').trigger('click');
        await flushPromises();

        expect(visit).toHaveBeenCalledWith('/avisos?page=1');
        wrapper.unmount();
    });

    it('under «Sin leer», a notice marked read leaves like a hidden one', async () => {
        const wrapper = mountPage([item('a'), item('b')], 'unread');
        post.mockImplementation((_url, _data, options) => {
            void wrapper.setProps({ notifications: page([item('b')]) }).then(() => {
                options.onSuccess?.();
                options.onFinish?.();
            });
        });

        await wrapper.findAll('[data-testid="site-notification-menu"]')[0]?.trigger('click');
        await wrapper.get('[data-testid="site-notification-toggle-read"]').trigger('click');
        await flushPromises();

        expect(post).toHaveBeenCalledWith('/avisos/a/leido', {}, expect.anything());
        expect(document.activeElement).toBe(
            wrapper.get('[data-testid="site-notification"]').element
        );
        wrapper.unmount();
    });

    it('under «Sin leer», reading the last notice of a later page goes back a page', async () => {
        const wrapper = mountPage([item('a')], 'unread');
        await wrapper.setProps({
            notifications: page([item('a')], '/avisos?filtro=sin-leer&page=1'),
        });
        post.mockImplementation((_url, _data, options) => {
            void wrapper
                .setProps({ notifications: page([], '/avisos?filtro=sin-leer&page=1') })
                .then(() => {
                    options.onSuccess?.();
                    options.onFinish?.();
                });
        });

        await wrapper.get('[data-testid="site-notification-menu"]').trigger('click');
        await wrapper.get('[data-testid="site-notification-toggle-read"]').trigger('click');
        await flushPromises();

        expect(visit).toHaveBeenCalledWith('/avisos?filtro=sin-leer&page=1');
        wrapper.unmount();
    });

    it('moves the groups on at midnight while the page stays open', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-11-19T22:59:00Z'));
        const wrapper = mountPage([item('a', { notifiedAt: '2026-11-19T20:00:00+01:00' })]);
        expect(wrapper.get('[data-testid="site-notifications-day"] h2').text()).toBe('Hoy');

        // 00:00 in Madrid
        await vi.advanceTimersByTimeAsync(2 * 60_000);

        expect(wrapper.get('[data-testid="site-notifications-day"] h2').text()).toBe('Ayer');
        wrapper.unmount();
    });

    it('gives the focus to the title when the last notice is hidden', async () => {
        const wrapper = mountPage([item('a')]);
        post.mockImplementation((_url, _data, options) => {
            void wrapper.setProps({ notifications: page([]), unread: 0 }).then(() => {
                options.onSuccess?.();
                options.onFinish?.();
            });
        });

        await wrapper.get('[data-testid="site-notification-menu"]').trigger('click');
        await wrapper.get('[data-testid="site-notification-hide"]').trigger('click');
        await flushPromises();

        expect(document.activeElement).toBe(wrapper.get('h1').element);
        expect(visit).not.toHaveBeenCalled();
        wrapper.unmount();
    });

    afterEach(() => {
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('puts the notices under their day in the display zone', () => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-11-19T09:00:00Z'));
        const wrapper = mountPage([
            item('a', { notifiedAt: '2026-11-19T08:00:00+01:00' }),
            item('b', { notifiedAt: '2026-11-18T08:00:00+01:00' }),
            item('c', { notifiedAt: '2026-11-16T08:00:00+01:00' }),
            item('d', { notifiedAt: '2026-11-02T08:00:00+01:00' }),
        ]);

        const days = wrapper.findAll('[data-testid="site-notifications-day"]');
        expect(days.map((day) => day.get('h2').text())).toEqual([
            'Hoy',
            'Ayer',
            'Esta semana',
            'Antes',
        ]);
        expect(days[0]?.attributes('aria-labelledby')).toBe(days[0]?.get('h2').attributes('id'));
        expect(wrapper.findAll('[data-testid="site-notification"]')).toHaveLength(4);
        wrapper.unmount();
    });

    it('marks the current filter and links to the other', () => {
        const all = mountPage([item('a')]);
        const links = all.get('[data-testid="site-notifications-filter"]').findAll('a');
        expect(links.map((link) => [link.text(), link.attributes('href')])).toEqual([
            ['Todos', '/avisos'],
            ['Sin leer', '/avisos?filtro=sin-leer'],
        ]);
        expect(links[0]?.attributes('aria-current')).toBe('page');
        expect(links[1]?.attributes('aria-current')).toBeUndefined();
        all.unmount();

        const unread = mountPage([item('a')], 'unread');
        const unreadLinks = unread.get('[data-testid="site-notifications-filter"]').findAll('a');
        expect(unreadLinks[1]?.attributes('aria-current')).toBe('page');
        unread.unmount();
    });

    it('says the person is up to date when nothing is unread', () => {
        const wrapper = mountPage([], 'unread');

        expect(wrapper.get('[data-testid="site-notifications-empty"]').text()).toContain(
            'No tienes avisos sin leer'
        );
        expect(wrapper.get('[data-testid="site-notifications-empty"]').text()).toContain(
            'Estás al día.'
        );
        wrapper.unmount();
    });

    it('hides a notice through the server, keeping the scroll, and says so', async () => {
        post.mockImplementation((_url, _data, options) => {
            options.onSuccess?.();
            options.onFinish?.();
        });
        const wrapper = mountPage([item('a'), item('b')]);

        await wrapper.findAll('[data-testid="site-notification-menu"]')[0]?.trigger('click');
        await wrapper.get('[data-testid="site-notification-hide"]').trigger('click');
        await flushPromises();

        expect(post).toHaveBeenCalledWith(
            '/avisos/a/ocultar',
            {},
            expect.objectContaining({ preserveScroll: true })
        );
        expect(wrapper.get('[role="status"]').text()).toBe('Aviso oculto.');
        wrapper.unmount();
    });

    it('marks a read notice unread again', async () => {
        const wrapper = mountPage([item('a', { unread: false })]);

        await wrapper.get('[data-testid="site-notification-menu"]').trigger('click');
        await wrapper.get('[data-testid="site-notification-toggle-read"]').trigger('click');

        expect(post).toHaveBeenCalledWith('/avisos/a/no-leido', {}, expect.anything());
        wrapper.unmount();
    });

    it('waits for one answer before sending another choice', async () => {
        const wrapper = mountPage([item('a'), item('b')]);
        const menus = wrapper.findAll('[data-testid="site-notification-menu"]');

        await menus[0]?.trigger('click');
        await wrapper.get('[data-testid="site-notification-toggle-read"]').trigger('click');
        await menus[1]?.trigger('click');
        await wrapper.find('[data-testid="site-notification-toggle-read"]').trigger('click');

        expect(post).toHaveBeenCalledTimes(1);
        wrapper.unmount();
    });
});
