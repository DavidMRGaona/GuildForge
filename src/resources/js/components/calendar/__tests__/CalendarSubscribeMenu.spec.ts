import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import CalendarSubscribeMenu from '../CalendarSubscribeMenu.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { CalendarSubscriptionLinks } from '@/types/calendarFeed';

const notifications = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): { url: string } => ({ url: '/calendario' }),
}));

vi.mock('@/composables/useNotifications', () => ({
    useNotifications: (): typeof notifications => notifications,
}));

const WEBCAL_URL = 'webcal://example.test/calendario.ics';

const links: CalendarSubscriptionLinks = {
    feedUrl: 'https://example.test/calendario.ics',
    webcalUrl: WEBCAL_URL,
    googleUrl: `https://calendar.google.com/calendar/r?cid=${encodeURIComponent(WEBCAL_URL)}`,
    outlookUrl:
        'https://outlook.live.com/calendar/0/addfromweb?url=https%3A%2F%2Fexample.test%2Fcalendario.ics',
};

let wrapper: VueWrapper | null = null;

function mountMenu(extraProps: Record<string, unknown> = {}): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });
    wrapper = mount(CalendarSubscribeMenu, {
        props: { links, ...extraProps },
        global: { plugins: [i18n] },
        attachTo: document.body,
    });

    return wrapper;
}

async function open(menu: VueWrapper): Promise<void> {
    await menu.find('button[aria-haspopup="menu"]').trigger('click');
}

beforeEach(() => {
    notifications.error.mockReset();
});

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

describe('CalendarSubscribeMenu', () => {
    it('starts closed', () => {
        const menu = mountMenu();

        expect(menu.find('[role="menu"]').exists()).toBe(false);
        expect(menu.find('button[aria-haspopup="menu"]').attributes('aria-expanded')).toBe('false');
        expect(menu.text()).toContain('Suscribirse');
    });

    it('offers webcal, Google Calendar and Outlook subscriptions', async () => {
        const menu = mountMenu();
        await open(menu);

        const hrefs = menu.findAll('a[role="menuitem"]').map((link) => link.attributes('href'));
        expect(hrefs).toEqual([links.webcalUrl, links.googleUrl, links.outlookUrl]);
    });

    it('opens the web calendars in a new tab, but not the webcal address', async () => {
        const menu = mountMenu();
        await open(menu);

        const [webcal, google, outlook] = menu.findAll('a[role="menuitem"]');
        expect(webcal?.attributes('target')).toBeUndefined();
        expect(google?.attributes('target')).toBe('_blank');
        expect(google?.attributes('rel')).toContain('noopener');
        expect(outlook?.attributes('target')).toBe('_blank');
    });

    it('copies the webcal address', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        const menu = mountMenu();
        await open(menu);

        await menu.find('button[role="menuitem"]').trigger('click');
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith(WEBCAL_URL);
        expect(menu.find('button[role="menuitem"]').text()).toContain('Dirección copiada');
    });

    it('says so when the address cannot be copied', async () => {
        const writeText = vi.fn().mockRejectedValue(new Error('Denied'));
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        vi.spyOn(console, 'error').mockImplementation(() => undefined);
        const menu = mountMenu();
        await open(menu);

        await menu.find('button[role="menuitem"]').trigger('click');
        await flushPromises();

        expect(notifications.error).toHaveBeenCalledWith('No se ha podido copiar la dirección.');
    });

    it('closes with Escape and gives the focus back to the button', async () => {
        const menu = mountMenu();
        await open(menu);

        await menu.trigger('keydown', { key: 'Escape' });

        expect(menu.find('[role="menu"]').exists()).toBe(false);
        expect(document.activeElement).toBe(menu.find('button[aria-haspopup="menu"]').element);
    });

    it('opens upwards for a button at the bottom of the content', async () => {
        const menu = mountMenu({ placement: 'top' });
        await open(menu);

        const panel = menu.find('[role="menu"]').element.parentElement;
        expect(panel?.classList.contains('bottom-full')).toBe(true);
        expect(panel?.classList.contains('top-full')).toBe(false);
    });
});
