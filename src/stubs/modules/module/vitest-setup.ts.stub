/* eslint-disable vue/one-component-per-file -- the stand-ins for Inertia's link and the site layout */
import { afterEach, beforeEach, vi } from 'vitest';
import type { VNode } from 'vue';
import { config, enableAutoUnmount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import coreEs from '@/locales/es';
import moduleLocales from '../../resources/js/locales';
import { page } from './support/page';

// Inertia is the core's: its router and links are replaced by spies and plain anchors
vi.mock('@inertiajs/vue3', async () => {
    const { defineComponent, h } = await import('vue');
    const { page: currentPage } = await import('./support/page');

    return {
        usePage: (): typeof currentPage => currentPage,
        router: { visit: vi.fn(), reload: vi.fn(), delete: vi.fn(), post: vi.fn(), get: vi.fn() },
        Link: defineComponent({
            // Inertia's own name, which the stand-in keeps
            // eslint-disable-next-line vue/multi-word-component-names, vue/no-reserved-component-names
            name: 'Link',
            props: { href: { type: String, required: true } },
            setup(props, { slots }) {
                return (): VNode => h('a', { href: props.href }, slots.default?.());
            },
        }),
    };
});

// The site layout (header, menus, stores) is not under test: only its slot is rendered
vi.mock('@/layouts/DefaultLayout.vue', async () => {
    const { defineComponent, h } = await import('vue');

    return {
        default: defineComponent({
            name: 'DefaultLayout',
            setup(_props, { slots }) {
                return (): VNode => h('div', slots.default?.());
            },
        }),
    };
});

// The core's texts with the module's on top, in Spanish, as the site loads them
config.global.plugins = [
    createI18n({
        legacy: false,
        locale: 'es',
        fallbackLocale: 'es',
        messages: { es: { ...coreEs, ...moduleLocales.es } },
    }),
];

// Every mounted component is unmounted after its test, attached ones included
enableAutoUnmount(afterEach);

beforeEach(() => {
    page.props = {};
});
