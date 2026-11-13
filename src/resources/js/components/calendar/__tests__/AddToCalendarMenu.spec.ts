import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import AddToCalendarMenu from '../AddToCalendarMenu.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { AddToCalendarLinks } from '@/types/models';

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): { url: string } => ({ url: '/' }),
}));

const links: AddToCalendarLinks = {
    google: 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=Torneo',
    outlook: 'https://outlook.live.com/calendar/0/deeplink/compose?subject=Torneo',
    ics: '/calendario/event/0198a1b2-0000-7000-8000-000000000001.ics',
};

let wrapper: VueWrapper | null = null;

function mountMenu(menuLinks: AddToCalendarLinks = links): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });
    wrapper = mount(AddToCalendarMenu, {
        props: { links: menuLinks },
        global: { plugins: [i18n] },
        attachTo: document.body,
    });

    return wrapper;
}

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

describe('AddToCalendarMenu', () => {
    it('starts closed', () => {
        const menu = mountMenu();

        expect(menu.get('button').attributes('aria-expanded')).toBe('false');
        expect(menu.find('[role="menu"]').exists()).toBe(false);
    });

    it('draws the trigger as a small outline button, like the other secondary actions', () => {
        const menu = mountMenu();

        const trigger = menu.getComponent(BaseButton);
        expect(trigger.props('variant')).toBe('outline');
        expect(trigger.props('size')).toBe('sm');
    });

    it('offers Google, Outlook and the .ics download', async () => {
        const menu = mountMenu();

        await menu.get('button').trigger('click');

        const items = menu.findAll('[role="menuitem"]');
        expect(items.map((item) => item.attributes('href'))).toEqual([
            links.google,
            links.outlook,
            links.ics,
        ]);
        expect(items[0]?.attributes('target')).toBe('_blank');
        expect(items[0]?.attributes('rel')).toContain('noopener');
        expect(items[2]?.attributes('target')).toBeUndefined();
    });

    it('leaves out the download when the entry cannot be downloaded', async () => {
        const menu = mountMenu({ ...links, ics: null });

        await menu.get('button').trigger('click');

        expect(menu.findAll('[role="menuitem"]')).toHaveLength(2);
    });

    it('closes with Escape and gives focus back to the button', async () => {
        const menu = mountMenu();
        await menu.get('button').trigger('click');

        await menu.get('[role="menu"]').trigger('keydown', { key: 'Escape' });

        expect(menu.find('[role="menu"]').exists()).toBe(false);
        expect(document.activeElement).toBe(menu.get('button').element);
    });

    it('closes when clicking outside', async () => {
        const menu = mountMenu();
        await menu.get('button').trigger('click');

        document.body.click();
        await menu.vm.$nextTick();

        expect(menu.find('[role="menu"]').exists()).toBe(false);
    });
});
