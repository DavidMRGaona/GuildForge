import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive } from 'vue';
import TheNavigation from '../TheNavigation.vue';
import { clearMenuBadge, setMenuBadge } from '@/composables/useMenuBadges';
import es from '@/locales/es';
import type { MenuItem } from '@/types/navigation';

function item(overrides: Partial<MenuItem>): MenuItem {
    return {
        id: 'x',
        label: 'Foro',
        href: '/foro',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
        key: 'forum:forum.index',
        ...overrides,
    };
}

const page = reactive<{ url: string; props: { navigation: { header: MenuItem[] } } }>({
    url: '/',
    props: { navigation: { header: [] } },
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): typeof page => page,
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('@/composables/useOnNavigate', () => ({ useOnNavigate: (): void => undefined }));

function mountMenu(header: MenuItem[], mobile = false): ReturnType<typeof mount> {
    page.props.navigation.header = header;

    return mount(TheNavigation, {
        props: { mobile },
        global: { plugins: [createI18n({ legacy: false, locale: 'es', messages: { es } })] },
    });
}

afterEach(() => {
    clearMenuBadge('forum:forum.index');
    clearMenuBadge('other:other.index');
});

describe('TheNavigation badges', () => {
    it('draws no mark unless a module switched one on', () => {
        const wrapper = mountMenu([item({})]);

        expect(wrapper.find('[data-testid="menu-badge"]').exists()).toBe(false);
    });

    it('marks the link by its key, with a dot hidden from screen readers and a text for them', () => {
        setMenuBadge('forum:forum.index', 'hay 3 temas sin leer');
        const wrapper = mountMenu([
            item({}),
            item({ id: 'y', label: 'Eventos', href: '/eventos', key: 'events:events.index' }),
        ]);

        const links = wrapper.findAll('a');
        expect(links[0]?.find('[data-testid="menu-badge"]').exists()).toBe(true);
        expect(links[0]?.find('[aria-hidden="true"]').exists()).toBe(true);
        expect(links[0]?.find('.sr-only').text()).toBe(', hay 3 temas sin leer');
        expect(links[1]?.find('[data-testid="menu-badge"]').exists()).toBe(false);
    });

    it('also marks the mobile menu', () => {
        setMenuBadge('forum:forum.index', 'hay temas sin leer');

        expect(
            mountMenu([item({})], true)
                .find('[data-testid="menu-badge"]')
                .exists()
        ).toBe(true);
    });

    it('follows the module: the mark appears and goes away without drawing the menu again', async () => {
        const wrapper = mountMenu([item({})]);

        setMenuBadge('forum:forum.index', 'algo nuevo');
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[data-testid="menu-badge"]').exists()).toBe(true);

        clearMenuBadge('forum:forum.index');
        await wrapper.vm.$nextTick();
        expect(wrapper.find('[data-testid="menu-badge"]').exists()).toBe(false);
    });

    it('survives a visit: a new menu with the same links keeps the marks', async () => {
        setMenuBadge('forum:forum.index', 'algo nuevo');
        const wrapper = mountMenu([item({})]);

        page.url = '/eventos';
        page.props.navigation.header = [item({ id: 'z' })];
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="menu-badge"]').exists()).toBe(true);
    });

    it('never marks a link that has no key', () => {
        setMenuBadge('forum:forum.index', 'algo nuevo');

        expect(
            mountMenu([item({ key: null })])
                .find('[data-testid="menu-badge"]')
                .exists()
        ).toBe(false);
    });

    it('marks the link and the button of a dropdown that holds it', () => {
        setMenuBadge('forum:forum.index', 'hay temas sin leer');
        const wrapper = mountMenu([
            item({
                id: 'group',
                label: 'Comunidad',
                href: '#',
                key: null,
                children: [item({ id: 'child' })],
            }),
        ]);

        expect(wrapper.find('button [data-testid="menu-badge"]').exists()).toBe(true);
        expect(wrapper.find('a [data-testid="menu-badge"]').exists()).toBe(true);
    });
});
