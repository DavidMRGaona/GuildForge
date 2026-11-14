import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import LinksBlock from '../LinksBlock.vue';
import TextBlock from '../TextBlock.vue';
import BrandBlock from '../BrandBlock.vue';
import ContactBlock from '../ContactBlock.vue';
import { FOOTER_BLOCK_TITLE_ID } from '../footerBlockTitle';
import en from '@/locales/en';
import es from '@/locales/es';
import type { MenuItem } from '@/types/navigation';

vi.mock('@inertiajs/vue3', () => ({
    Link: {
        props: ['href', 'target'],
        template: '<a data-inertia :href="href" :target="target"><slot /></a>',
    },
}));

const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

function item(overrides: Partial<MenuItem>): MenuItem {
    return {
        id: '1',
        label: 'Enlace',
        href: '/eventos',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
        key: null,
        ...overrides,
    };
}

describe('LinksBlock', () => {
    it('draws internal links with Inertia and external ones as plain links', () => {
        const wrapper = mount(LinksBlock, {
            global: { plugins: [i18n] },
            props: {
                items: [
                    item({ id: '1', label: 'Eventos', href: '/eventos' }),
                    item({
                        id: '2',
                        label: 'Discord',
                        href: 'https://discord.gg/ejemplo',
                        target: '_blank',
                        children: [item({ id: '3', label: 'Normas', href: '/normas' })],
                    }),
                ],
            },
        });

        const links = wrapper.findAll('a');
        expect(links.map((link) => link.text())).toEqual(['Eventos', 'Discord', 'Normas']);
        expect(links[0]?.attributes('data-inertia')).toBeDefined();
        expect(links[1]?.attributes('data-inertia')).toBeUndefined();
        expect(links[1]?.attributes('rel')).toBe('noopener noreferrer');
        expect(links[2]?.element.parentElement?.className).toContain('ps-3');
    });

    it('is a navigation landmark named after the block title, or a generic name', () => {
        const items = [item({ id: '1', label: 'Eventos' })];
        const titled = mount(LinksBlock, {
            global: { plugins: [i18n], provide: { [FOOTER_BLOCK_TITLE_ID as symbol]: 'titulo-1' } },
            props: { items },
        });
        const untitled = mount(LinksBlock, { global: { plugins: [i18n] }, props: { items } });

        expect(titled.get('nav').attributes('aria-labelledby')).toBe('titulo-1');
        expect(titled.get('nav').attributes('aria-label')).toBeUndefined();
        expect(untitled.get('nav').attributes('aria-label')).toBe('Enlaces del pie');
    });

    it('draws nothing without links', () => {
        expect(
            mount(LinksBlock, { global: { plugins: [i18n] }, props: { items: [] } })
                .find('ul')
                .exists()
        ).toBe(false);
    });
});

describe('TextBlock', () => {
    it('draws the sanitized text', () => {
        const wrapper = mount(TextBlock, { props: { html: '<p>Hola <strong>mundo</strong></p>' } });

        expect(wrapper.html()).toContain('<strong>mundo</strong>');
    });

    it('draws nothing for an empty text', () => {
        expect(
            mount(TextBlock, { props: { html: '' } })
                .find('div')
                .exists()
        ).toBe(false);
    });
});

describe('BrandBlock', () => {
    it('draws the logo, name, text and networks', () => {
        const wrapper = mount(BrandBlock, {
            props: {
                logo: '/images/defaults/logo-dark.png',
                name: 'Asociación de ejemplo',
                description: 'Juegos de mesa\ny rol',
                socialLinks: { discord: 'https://discord.gg/ejemplo' },
            },
        });

        expect(wrapper.get('img').attributes('alt')).toBe('Asociación de ejemplo');
        expect(wrapper.text()).toContain('Asociación de ejemplo');
        expect(wrapper.text()).toContain('Juegos de mesa');
        expect(wrapper.find('a[href="https://discord.gg/ejemplo"]').exists()).toBe(true);
    });

    it('leaves out what is switched off', () => {
        const wrapper = mount(BrandBlock, {
            props: { logo: null, name: null, description: null, socialLinks: {} },
        });

        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.find('a').exists()).toBe(false);
    });
});

describe('ContactBlock', () => {
    it('links the email, the phone and the contact form', () => {
        const wrapper = mount(ContactBlock, {
            global: { plugins: [i18n] },
            props: {
                email: 'hola@example.com',
                phone: '600 000 000',
                address: 'Calle Mayor 1\n28001 Madrid',
                contactUrl: '/nosotros#contact-section',
            },
        });

        expect(wrapper.get('a[href="mailto:hola@example.com"]').text()).toBe('hola@example.com');
        expect(wrapper.find('a[href="tel:600000000"]').exists()).toBe(true);
        expect(wrapper.text()).toContain('Calle Mayor 1');
        expect(wrapper.get('a[href="/nosotros#contact-section"]').text()).toBe('Escríbenos');
    });

    it('leaves out what is missing', () => {
        const wrapper = mount(ContactBlock, {
            global: { plugins: [i18n] },
            props: { email: null, phone: null, address: null, contactUrl: null },
        });

        expect(wrapper.find('a').exists()).toBe(false);
    });
});
