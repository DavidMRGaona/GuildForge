import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive } from 'vue';
import NotificationBell from '../NotificationBell.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import {
    DELIVERED_EVENT,
    HIDDEN_POLL_INTERVAL_MS,
    POLL_INTERVAL_MS,
    resetSiteNotificationsState,
} from '@/composables/useSiteNotifications';
import { resetRealtimeConnection, setRealtimeClientFactory } from '@/utils/realtime/connection';
import type {
    RawListener,
    RealtimeChannelHandle,
    RealtimeClient,
    RealtimeConnectionState,
} from '@/utils/realtime/client';
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

function hideTab(): void {
    Object.defineProperty(document, 'visibilityState', {
        configurable: true,
        get: () => 'hidden',
    });
}

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
        resetSiteNotificationsState();
        resetRealtimeConnection();
        setRealtimeClientFactory(null);
        vi.unstubAllGlobals();
        vi.useRealTimers();
        Reflect.deleteProperty(document, 'visibilityState');
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

    it('keeps two lines of each excerpt and gives the full date on the time', async () => {
        fetchMock.mockResolvedValue(
            jsonResponse({
                unread: 1,
                items: [notice({ notifiedAt: '2026-12-19T18:00:00+01:00' })],
            })
        );
        const wrapper = mountBell();

        await wrapper.get('[data-testid="notification-bell"]').trigger('click');
        await flushPromises();

        const excerpt = wrapper.get('[data-testid="site-notification-body"]');
        expect(excerpt.classes()).toContain('line-clamp-2');
        expect(excerpt.classes()).not.toContain('truncate');
        const time = wrapper.get('[data-testid="site-notification"] time');
        expect(time.attributes('datetime')).toBe('2026-12-19T18:00:00+01:00');
        expect(time.attributes('title')).toBe('19/12/2026, 18:00');
        wrapper.unmount();
    });

    it('moves the times on while the list is open', async () => {
        vi.useFakeTimers();
        vi.setSystemTime(new Date('2026-11-14T10:00:00Z'));
        fetchMock.mockResolvedValue(
            jsonResponse({ unread: 1, items: [notice({ notifiedAt: '2026-11-14T09:59:30Z' })] })
        );
        const wrapper = mountBell();

        await wrapper.get('[data-testid="notification-bell"]').trigger('click');
        await flushPromises();
        expect(wrapper.get('[data-testid="site-notification"] time').text()).toBe('ahora');

        await vi.advanceTimersByTimeAsync(2 * 60_000);
        await flushPromises();

        expect(wrapper.get('[data-testid="site-notification"] time').text()).toBe('hace 2 minutos');
        wrapper.unmount();
    });

    it('links to the notice settings from its header', async () => {
        fetchMock.mockResolvedValue(jsonResponse({ unread: 0, items: [] }));
        const wrapper = mountBell();

        await wrapper.get('[data-testid="notification-bell"]').trigger('click');
        await flushPromises();

        const settings = wrapper.get('[data-testid="notification-settings"]');
        expect(settings.attributes('href')).toBe('/perfil?tab=mail');
        expect(settings.attributes('aria-label')).toBe('Elegir qué avisos y correos recibes');
        wrapper.unmount();
    });

    it('marks one notice unread again from its menu', async () => {
        fetchMock
            .mockResolvedValueOnce(jsonResponse({ unread: 0, items: [notice({ unread: false })] }))
            .mockResolvedValueOnce(jsonResponse({ unread: 1 }));
        const wrapper = mountBell();
        await wrapper.get('[data-testid="notification-bell"]').trigger('click');
        await flushPromises();

        await wrapper.get('[data-testid="site-notification-menu"]').trigger('click');
        expect(wrapper.get('[data-testid="site-notification-toggle-read"]').text()).toBe(
            'Marcar como no leído'
        );
        await wrapper.get('[data-testid="site-notification-toggle-read"]').trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenLastCalledWith(
            '/avisos/0193b2c4-7f1e-7a00-9c3d-5e6f7a8b9c0d/no-leido',
            expect.objectContaining({ method: 'POST' })
        );
        expect(wrapper.get('[data-testid="site-notification"]').attributes('data-unread')).toBe(
            'true'
        );
        expect(wrapper.get('[data-testid="notification-badge"]').text()).toBe('1');
        expect(wrapper.get('[role="status"]').text()).toBe('Aviso marcado como no leído.');
        // The keyboard stays in the bell: back on the «⋯» it came from
        expect(document.activeElement).toBe(
            wrapper.get('[data-testid="site-notification-menu"]').element
        );
        wrapper.unmount();
    });

    it('hides a notice and moves the focus to the one in its place', async () => {
        fetchMock
            .mockResolvedValueOnce(
                jsonResponse({
                    unread: 2,
                    items: [notice(), notice({ id: 'b', href: '/avisos/b', title: 'Segundo' })],
                })
            )
            .mockResolvedValueOnce(jsonResponse({ unread: 1 }));
        const wrapper = mountBell();
        await wrapper.get('[data-testid="notification-bell"]').trigger('click');
        await flushPromises();

        await wrapper.findAll('[data-testid="site-notification-menu"]')[0]?.trigger('click');
        await wrapper.get('[data-testid="site-notification-hide"]').trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenLastCalledWith(
            '/avisos/0193b2c4-7f1e-7a00-9c3d-5e6f7a8b9c0d/ocultar',
            expect.objectContaining({ method: 'POST' })
        );
        const entries = wrapper.findAll('[data-testid="site-notification"]');
        expect(entries).toHaveLength(1);
        expect(document.activeElement).toBe(entries[0]?.element);
        expect(wrapper.get('[data-testid="notification-badge"]').text()).toBe('1');
        expect(wrapper.get('[role="status"]').text()).toBe('Aviso oculto.');
        wrapper.unmount();
    });

    it('places the «⋯» over the row through a wrapper, never on the menu root itself', async () => {
        fetchMock.mockResolvedValue(jsonResponse({ unread: 1, items: [notice()] }));
        const wrapper = mountBell();
        await wrapper.get('[data-testid="notification-bell"]').trigger('click');
        await flushPromises();

        // Two position classes on one element: the compiled CSS lets `relative` win
        const menuRoot = wrapper.get('[data-testid="site-notification-menu"]').element
            .parentElement;
        expect(menuRoot?.classList.contains('relative')).toBe(true);
        expect(menuRoot?.classList.contains('absolute')).toBe(false);
        expect(menuRoot?.parentElement?.classList.contains('absolute')).toBe(true);
        wrapper.unmount();
    });

    it('closes the menu with Escape and leaves the bell open', async () => {
        fetchMock.mockResolvedValue(jsonResponse({ unread: 1, items: [notice()] }));
        const wrapper = mountBell();
        await wrapper.get('[data-testid="notification-bell"]').trigger('click');
        await flushPromises();
        const menu = wrapper.get('[data-testid="site-notification-menu"]');

        await menu.trigger('click');
        await wrapper
            .get('[data-testid="site-notification-hide"]')
            .trigger('keydown', { key: 'Escape' });
        await flushPromises();

        expect(wrapper.find('[data-testid="site-notification-hide"]').exists()).toBe(false);
        expect(menu.attributes('aria-expanded')).toBe('false');
        expect(document.activeElement).toBe(menu.element);
        expect(wrapper.get('[data-testid="notification-bell"]').attributes('aria-expanded')).toBe(
            'true'
        );
        wrapper.unmount();
    });

    it('keeps the notice and says so when the server refuses', async () => {
        fetchMock
            .mockResolvedValueOnce(jsonResponse({ unread: 1, items: [notice()] }))
            .mockResolvedValueOnce(jsonResponse({ message: 'Error' }, 500));
        const wrapper = mountBell();
        await wrapper.get('[data-testid="notification-bell"]').trigger('click');
        await flushPromises();

        await wrapper.get('[data-testid="site-notification-menu"]').trigger('click');
        await wrapper.get('[data-testid="site-notification-hide"]').trigger('click');
        await flushPromises();

        expect(wrapper.findAll('[data-testid="site-notification"]')).toHaveLength(1);
        expect(wrapper.get('[role="status"]').text()).toBe(
            'No se ha podido cambiar el aviso. Inténtalo de nuevo.'
        );
        expect(document.activeElement).toBe(
            wrapper.get('[data-testid="site-notification-menu"]').element
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

    it('keeps asking every few minutes while the tab is hidden, for the tab title', async () => {
        vi.useFakeTimers();
        fetchMock.mockResolvedValue(jsonResponse({ unread: 6 }));
        const wrapper = mountBell();
        hideTab();

        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS);
        expect(fetchMock).toHaveBeenCalledTimes(1);
        expect(wrapper.find('[data-testid="notification-badge"]').text()).toBe('6');

        await vi.advanceTimersByTimeAsync(HIDDEN_POLL_INTERVAL_MS - POLL_INTERVAL_MS);
        expect(fetchMock).toHaveBeenCalledTimes(1);

        await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS);
        expect(fetchMock).toHaveBeenCalledTimes(2);
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

    describe('with real time', () => {
        const CHANNEL = 'core.site-notifications.user-1';
        let granted: Array<() => void>;
        let events: Map<string, RawListener>;
        let stateListeners: Array<(state: RealtimeConnectionState) => void>;
        let subscriptions: string[];

        function fakeClient(): RealtimeClient {
            let current: RealtimeConnectionState = 'connecting';

            return {
                state: () => current,
                onStateChange: (callback): void => {
                    stateListeners.push((state) => {
                        current = state;
                        callback(state);
                    });
                },
                subscribe: (name): RealtimeChannelHandle => {
                    subscriptions.push(name);

                    return {
                        onSubscribed: (callback) => granted.push(callback),
                        onError: () => undefined,
                        listen: (event, callback) => events.set(event, callback),
                        stopListening: (event) => events.delete(event),
                        whisper: () => undefined,
                        listenForWhisper: () => undefined,
                        stopListeningForWhisper: () => undefined,
                    };
                },
                leave: () => undefined,
                reconnect: () => undefined,
                disconnect: () => undefined,
            };
        }

        async function goLive(): Promise<void> {
            await flushPromises();
            stateListeners.forEach((listener) => listener('connected'));
            granted.forEach((grant) => grant());
            await flushPromises();
        }

        beforeEach(() => {
            granted = [];
            events = new Map();
            stateListeners = [];
            subscriptions = [];
            setRealtimeClientFactory(async () => fakeClient());
            page.props = {
                siteNotifications: { unread: 3, channel: CHANNEL },
                realtime: {
                    key: 'example-key',
                    host: 'example.test',
                    port: 443,
                    tls: true,
                    authEndpoint: '/broadcasting/auth',
                },
                auth: { user: { id: 'user-1' } },
            };
        });

        it('asks for the counter as soon as a notice is delivered', async () => {
            fetchMock.mockResolvedValue(jsonResponse({ unread: 4 }));
            const wrapper = mountBell();
            await goLive();

            expect(subscriptions).toEqual([CHANNEL]);
            events.get(DELIVERED_EVENT)?.({});
            await flushPromises();

            expect(fetchMock).toHaveBeenCalledWith('/avisos/contador', expect.anything());
            expect(wrapper.get('[data-testid="notification-badge"]').text()).toBe('4');
            wrapper.unmount();
        });

        it('hears a delivered notice in a hidden tab too', async () => {
            fetchMock.mockResolvedValue(jsonResponse({ unread: 4 }));
            const wrapper = mountBell();
            await goLive();
            hideTab();

            events.get(DELIVERED_EVENT)?.({});
            await flushPromises();

            expect(fetchMock).toHaveBeenCalledWith('/avisos/contador', expect.anything());
            expect(wrapper.get('[data-testid="notification-badge"]').text()).toBe('4');
            wrapper.unmount();
        });

        it('skips the minute question while live and asks again once it is not', async () => {
            vi.useFakeTimers();
            fetchMock.mockResolvedValue(jsonResponse({ unread: 3 }));
            const wrapper = mountBell();
            await goLive();

            await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS);
            expect(fetchMock).not.toHaveBeenCalled();

            stateListeners.forEach((listener) => listener('unavailable'));
            await vi.advanceTimersByTimeAsync(POLL_INTERVAL_MS);
            expect(fetchMock).toHaveBeenCalledWith('/avisos/contador', expect.anything());
            wrapper.unmount();
        });

        it('subscribes once for the two bells of the page', async () => {
            const desktop = mountBell();
            const narrow = mountBell();
            await goLive();

            expect(subscriptions).toEqual([CHANNEL]);
            desktop.unmount();
            narrow.unmount();
        });
    });
});
