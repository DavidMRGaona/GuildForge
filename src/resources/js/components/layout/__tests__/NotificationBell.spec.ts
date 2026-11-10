import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive } from 'vue';
import NotificationBell from '../NotificationBell.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import { POLL_INTERVAL_MS, resetSiteNotificationsState } from '@/composables/useSiteNotifications';
import type { SiteNotificationItem } from '@/types/siteNotifications';

const page = reactive<{ url: string; props: Record<string, unknown> }>({
    url: '/',
    props: { siteNotifications: { unread: 3 } },
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): typeof page => page,
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

function notice(overrides: Partial<SiteNotificationItem> = {}): SiteNotificationItem {
    return {
        id: '0193b2c4-7f1e-7a00-9c3d-5e6f7a8b9c0d',
        title: 'Ana respondió en «La tumba»',
        body: 'Entro en la cripta.',
        icon: 'message-square',
        count: 1,
        unread: true,
        notifiedAt: new Date().toISOString(),
        href: '/avisos/0193b2c4-7f1e-7a00-9c3d-5e6f7a8b9c0d',
        ...overrides,
    };
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

let fetchMock: ReturnType<typeof vi.fn>;

function mountBell(): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(NotificationBell, { global: { plugins: [i18n] }, attachTo: document.body });
}

describe('NotificationBell', () => {
    beforeEach(() => {
        resetSiteNotificationsState();
        page.props = { siteNotifications: { unread: 3 } };
        fetchMock = vi.fn();
        vi.stubGlobal('fetch', fetchMock);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        vi.useRealTimers();
        document.body.innerHTML = '';
    });

    it('shows the unread count of the page and says it to screen readers', () => {
        const wrapper = mountBell();

        expect(wrapper.get('[data-testid="notification-badge"]').text()).toBe('3');
        expect(wrapper.get('[data-testid="notification-bell"]').attributes('aria-label')).toBe(
            'Avisos, 3 sin leer'
        );
        wrapper.unmount();
    });

    it('caps the badge at 9+ and hides it with nothing unread', async () => {
        page.props = { siteNotifications: { unread: 12 } };
        const wrapper = mountBell();

        expect(wrapper.get('[data-testid="notification-badge"]').text()).toBe('9+');

        page.props = { siteNotifications: { unread: 0 } };
        await flushPromises();

        expect(wrapper.find('[data-testid="notification-badge"]').exists()).toBe(false);
        expect(wrapper.get('[data-testid="notification-bell"]').attributes('aria-label')).toBe(
            'Avisos'
        );
        wrapper.unmount();
    });

    it('opens the latest notices and links each one through the core', async () => {
        fetchMock.mockResolvedValue(
            jsonResponse({
                unread: 2,
                items: [notice(), notice({ id: 'b', href: '/avisos/b', unread: false, count: 4 })],
            })
        );
        const wrapper = mountBell();

        await wrapper.get('[data-testid="notification-bell"]').trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith('/avisos/recientes', expect.anything());
        const entries = wrapper.findAll('[data-testid="site-notification"]');
        expect(entries).toHaveLength(2);
        expect(entries[0]?.attributes('href')).toBe('/avisos/0193b2c4-7f1e-7a00-9c3d-5e6f7a8b9c0d');
        expect(entries[0]?.text()).toContain('Sin leer:');
        expect(entries[1]?.get('[data-testid="site-notification-count"]').text()).toBe(
            'y 3 avisos más'
        );
        expect(wrapper.get('[data-testid="notification-badge"]').text()).toBe('2');
        expect(wrapper.get('[data-testid="notification-bell"]').attributes('aria-expanded')).toBe(
            'true'
        );
        wrapper.unmount();
    });

    it('says when there is nothing yet', async () => {
        fetchMock.mockResolvedValue(jsonResponse({ unread: 0, items: [] }));
        const wrapper = mountBell();

        await wrapper.get('[data-testid="notification-bell"]').trigger('click');
        await flushPromises();

        expect(wrapper.text()).toContain('No tienes avisos');
        wrapper.unmount();
    });

    it('closes with Escape and gives the focus back to the bell', async () => {
        fetchMock.mockResolvedValue(jsonResponse({ unread: 1, items: [notice()] }));
        const wrapper = mountBell();
        const bell = wrapper.get('[data-testid="notification-bell"]');

        await bell.trigger('click');
        await flushPromises();
        await wrapper
            .get('[data-testid="site-notification"]')
            .trigger('keydown', { key: 'Escape' });
        await flushPromises();

        expect(bell.attributes('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(bell.element);
        wrapper.unmount();
    });

    it('moves between the notices with the arrows', async () => {
        fetchMock.mockResolvedValue(
            jsonResponse({ unread: 2, items: [notice(), notice({ id: 'b', href: '/avisos/b' })] })
        );
        const wrapper = mountBell();
        await wrapper.get('[data-testid="notification-bell"]').trigger('click');
        await flushPromises();
        const entries = wrapper.findAll('[data-testid="site-notification"]');

        await wrapper
            .get('[data-testid="notification-bell"]')
            .trigger('keydown', { key: 'ArrowDown' });
        expect(document.activeElement).toBe(entries[0]?.element);

        await entries[0]?.trigger('keydown', { key: 'ArrowDown' });
        expect(document.activeElement).toBe(entries[1]?.element);

        await entries[1]?.trigger('keydown', { key: 'ArrowDown' });
        expect(document.activeElement).toBe(entries[0]?.element);
        wrapper.unmount();
    });

    it('marks everything read', async () => {
        fetchMock
            .mockResolvedValueOnce(jsonResponse({ unread: 1, items: [notice()] }))
            .mockResolvedValueOnce(jsonResponse({ unread: 0 }));
        const wrapper = mountBell();
        await wrapper.get('[data-testid="notification-bell"]').trigger('click');
        await flushPromises();

        const markAll = wrapper
            .findAll('button')
            .find((button) => button.text() === 'Marcar todo como leído');
        await markAll?.trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenLastCalledWith(
            '/avisos/leidos',
            expect.objectContaining({ method: 'POST' })
        );
        expect(wrapper.find('[data-testid="notification-badge"]').exists()).toBe(false);
        expect(wrapper.get('[data-testid="site-notification"]').attributes('data-unread')).toBe(
            'false'
        );
        expect(wrapper.get('[role="status"]').text()).toBe('Todos los avisos están leídos.');
        wrapper.unmount();
    });

    it('asks for the counter every minute and announces a rise', async () => {
        vi.useFakeTimers();
        fetchMock.mockResolvedValue(jsonResponse({ unread: 5 }));
        const wrapper = mountBell();

        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS);
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith('/avisos/contador', expect.anything());
        expect(wrapper.get('[data-testid="notification-badge"]').text()).toBe('5');
        expect(wrapper.get('[role="status"]').text()).toBe('Tienes 5 avisos sin leer');
        wrapper.unmount();
    });

    it('stops asking once unmounted', async () => {
        vi.useFakeTimers();
        fetchMock.mockResolvedValue(jsonResponse({ unread: 5 }));
        const wrapper = mountBell();
        wrapper.unmount();

        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS * 2);

        expect(fetchMock).not.toHaveBeenCalled();
    });
});
