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

vi.mock('@inertiajs/vue3', () => ({
    router: {
        post: (url: string, data: object, options: VisitOptions): void => post(url, data, options),
        get: vi.fn(),
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

function mountPage(items: SiteNotificationItem[]): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(Index, {
        props: {
            unread: items.filter((entry) => entry.unread).length,
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
    });

    afterEach(() => {
        document.body.innerHTML = '';
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
