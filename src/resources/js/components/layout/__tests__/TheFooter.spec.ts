import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive } from 'vue';
import TheFooter from '../TheFooter.vue';
import en from '@/locales/en';
import es from '@/locales/es';

const page = reactive<{ url: string; props: Record<string, unknown> }>({ url: '/', props: {} });

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): typeof page => page,
    Link: {
        props: ['href', 'target'],
        template: '<a :href="href" :target="target"><slot /></a>',
    },
}));

function mountFooter(): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(TheFooter, { global: { plugins: [i18n] } });
}

const fixedFooterProps = {
    appName: 'Asociación de ejemplo',
    navigation: {
        header: [],
        footer: [
            {
                id: '1',
                label: 'Contacto',
                href: '/nosotros',
                target: '_self',
                icon: null,
                children: [],
                isActive: true,
                key: null,
            },
        ],
    },
    socialLinks: { discord: 'https://discord.gg/ejemplo', facebook: '' },
};

function textBlock(html: string): Record<string, unknown> {
    return {
        key: 'core.text',
        module: null,
        component: 'components/siteBlocks/TextBlock.vue',
        props: { html },
    };
}

describe('TheFooter', () => {
    beforeEach(() => {
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-11-14T12:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    describe('without a configured footer', () => {
        it('draws the fixed footer exactly as before', () => {
            page.props = { ...fixedFooterProps, footer: null };

            expect(mountFooter().html()).toMatchSnapshot();
        });

        it('draws the same when the prop is missing', () => {
            page.props = { ...fixedFooterProps };
            const withoutProp = mountFooter().html();

            page.props = { ...fixedFooterProps, footer: null };

            expect(withoutProp).toBe(mountFooter().html());
        });

        it('falls back to the default links without a footer menu', () => {
            page.props = {
                ...fixedFooterProps,
                navigation: { header: [], footer: [] },
                footer: null,
            };

            const links = mountFooter()
                .findAll('nav a')
                .map((link) => link.attributes('href'));

            expect(links).toEqual(['/eventos', '/articulos', '/galeria', '/nosotros']);
        });
    });

    describe('with a configured footer', () => {
        beforeEach(() => {
            page.props = {
                ...fixedFooterProps,
                footer: {
                    columns: [
                        { blocks: [{ title: 'Quiénes somos', block: textBlock('<p>Uno</p>') }] },
                        { blocks: [{ title: null, block: textBlock('<p>Dos</p>') }] },
                        { blocks: [{ title: 'Tres', block: textBlock('<p>Tres</p>') }] },
                    ],
                    siteName: 'Asociación configurada',
                    legalLinks: [
                        { label: 'Aviso legal', href: '/aviso-legal' },
                        { label: 'Política de privacidad', href: '/politica-de-privacidad' },
                    ],
                },
            };
        });

        it('draws its columns with their titles and blocks', async () => {
            const wrapper = mountFooter();
            // Core blocks are loaded on demand from the core build
            await vi.waitFor(async () => {
                await flushPromises();
                expect(wrapper.html()).toContain('<p>Tres</p>');
            });

            const columns = wrapper.findAll('[data-footer-column]');
            expect(columns).toHaveLength(3);
            expect(columns[0]?.get('h2').text()).toBe('Quiénes somos');
            expect(columns[1]?.find('h2').exists()).toBe(false);
            expect(columns[0]?.html()).toContain('<p>Uno</p>');
            expect(wrapper.text()).not.toContain('Contacto');
        });

        it('lays the columns out one, two and then as configured', () => {
            const grid = mountFooter().get('[data-footer-columns]');

            expect(grid.classes()).toEqual(
                expect.arrayContaining(['grid-cols-1', 'sm:grid-cols-2', 'lg:grid-cols-3'])
            );
        });

        it('keeps a single column on one track', () => {
            page.props = {
                ...page.props,
                footer: {
                    columns: [{ blocks: [{ title: null, block: textBlock('<p>Uno</p>') }] }],
                    siteName: 'Asociación configurada',
                    legalLinks: [],
                },
            };

            const grid = mountFooter().get('[data-footer-columns]');

            expect(grid.classes()).not.toContain('sm:grid-cols-2');
            expect(grid.classes()).toContain('lg:grid-cols-1');
        });

        it('ends with the copyright and the published legal pages', () => {
            const bar = mountFooter().get('[data-footer-bottom]');

            expect(bar.text()).toContain(
                '© 2026 Asociación configurada. Todos los derechos reservados.'
            );
            expect(bar.findAll('a').map((link) => [link.text(), link.attributes('href')])).toEqual([
                ['Aviso legal', '/aviso-legal'],
                ['Política de privacidad', '/politica-de-privacidad'],
            ]);
        });

        it('draws the fixed footer when no block is left to draw', () => {
            page.props = {
                ...fixedFooterProps,
                footer: { columns: [], siteName: 'Asociación configurada', legalLinks: [] },
            };
            const empty = mountFooter().html();

            page.props = { ...fixedFooterProps, footer: null };

            expect(empty).toBe(mountFooter().html());
        });

        it('names each titled block after its heading', () => {
            const wrapper = mountFooter();
            const sections = wrapper.findAll('[data-footer-column] section');

            const titled = sections[0];
            const heading = titled?.get('h2');
            expect(titled?.attributes('aria-labelledby')).toBe(heading?.attributes('id'));
            expect(heading?.attributes('id')).toBeTruthy();
            expect(sections[1]?.attributes('aria-labelledby')).toBeUndefined();
        });
    });
});
