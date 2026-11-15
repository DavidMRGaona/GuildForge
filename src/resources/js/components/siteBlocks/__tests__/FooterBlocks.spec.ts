import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import LinksBlock from '../LinksBlock.vue';
import TextBlock from '../TextBlock.vue';
import BrandBlock from '../BrandBlock.vue';
import ContactBlock from '../ContactBlock.vue';
import UpcomingEventsBlock from '../UpcomingEventsBlock.vue';
import ButtonBlock from '../ButtonBlock.vue';
import LogosBlock from '../LogosBlock.vue';
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

    it('keeps light text on the dark footer in both colour schemes', () => {
        const classes = mount(TextBlock, { props: { html: '<p>Hola</p>' } })
            .get('div')
            .classes();

        // The site's prose follows the page's scheme and would draw dark text in light mode
        expect(classes).not.toContain('prose');
        expect(classes).toContain('text-neutral-300');
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

describe('UpcomingEventsBlock', () => {
    const events = [
        {
            id: '1',
            title: 'Noche de rol',
            url: '/eventos/noche-de-rol',
            startDate: '2026-06-16T18:00:00+02:00',
            location: 'Local de la asociación',
        },
        {
            id: '2',
            title: 'Torneo',
            url: '/eventos/torneo',
            startDate: '2026-06-20T10:30:00+02:00',
            location: null,
        },
    ];

    it('draws each event as a link with its date in the display zone and its place', () => {
        const wrapper = mount(UpcomingEventsBlock, {
            global: { plugins: [i18n] },
            props: { events, allUrl: '/eventos' },
        });

        const links = wrapper.findAll('a');
        expect(links.map((link) => link.text())).toEqual([
            'Noche de rol',
            'Torneo',
            'Ver todos los eventos',
        ]);
        expect(links.map((link) => link.attributes('href'))).toEqual([
            '/eventos/noche-de-rol',
            '/eventos/torneo',
            '/eventos',
        ]);
        const times = wrapper.findAll('time');
        expect(times[0]?.attributes('datetime')).toBe('2026-06-16T18:00:00+02:00');
        expect(times[0]?.text()).toContain('18:00');
        expect(times[1]?.text()).toContain('10:30');
        expect(wrapper.text()).toContain('Local de la asociación');
    });

    it('is a navigation landmark named after the block title, or a generic name', () => {
        const generic = mount(UpcomingEventsBlock, {
            global: { plugins: [i18n] },
            props: { events, allUrl: null },
        });
        const titled = mount(UpcomingEventsBlock, {
            global: {
                plugins: [i18n],
                provide: { [FOOTER_BLOCK_TITLE_ID as symbol]: 'block-title' },
            },
            props: { events, allUrl: null },
        });

        expect(generic.get('nav').attributes('aria-label')).toBe('Próximos eventos');
        expect(titled.get('nav').attributes('aria-labelledby')).toBe('block-title');
        expect(titled.get('nav').attributes('aria-label')).toBeUndefined();
    });

    it('says there is nothing coming and keeps the link to every event', () => {
        const wrapper = mount(UpcomingEventsBlock, {
            global: { plugins: [i18n] },
            props: { events: [], allUrl: '/eventos' },
        });

        expect(wrapper.text()).toContain('No hay eventos próximos.');
        expect(wrapper.get('a').attributes('href')).toBe('/eventos');
    });

    it('draws nothing when there is nothing to say and no link', () => {
        const wrapper = mount(UpcomingEventsBlock, {
            global: { plugins: [i18n] },
            props: { events: [], allUrl: null },
        });

        expect(wrapper.find('nav').exists()).toBe(false);
    });
});

describe('ButtonBlock', () => {
    it('draws a path of the site with Inertia and an address as a plain link', () => {
        const internal = mount(ButtonBlock, {
            props: { label: 'Eventos', url: '/eventos', variant: 'primary' },
        });
        const external = mount(ButtonBlock, {
            props: {
                label: 'Únete',
                url: 'https://example.com/unete',
                variant: 'primary',
            },
        });

        expect(internal.get('a').attributes('data-inertia')).toBeDefined();
        expect(internal.get('a').attributes('href')).toBe('/eventos');
        expect(external.get('a').attributes('data-inertia')).toBeUndefined();
        expect(external.get('a').attributes('href')).toBe('https://example.com/unete');
        expect(external.text()).toBe('Únete');
    });

    it('tells the main style from the secondary one', () => {
        const primary = mount(ButtonBlock, {
            props: { label: 'A', url: '/a', variant: 'primary' },
        });
        const secondary = mount(ButtonBlock, {
            props: { label: 'A', url: '/a', variant: 'secondary' },
        });

        expect(primary.get('a').classes().join(' ')).toContain('--button-primary-bg');
        // Secondary stays light on the dark footer whatever the page's colour scheme
        expect(secondary.get('a').classes()).toContain('text-white');
        expect(secondary.get('a').classes().join(' ')).not.toContain('--button-primary-bg');
    });
});

describe('links that are not Inertia pages', () => {
    it('draws the panel, files and health checks as plain links in the button', () => {
        for (const url of [
            '/admin',
            '/admin/eventos',
            '/up',
            '/normas.pdf',
            '/storage/a.png',
            '/livewire/update',
        ]) {
            const wrapper = mount(ButtonBlock, { props: { label: 'A', url, variant: 'primary' } });

            expect(wrapper.get('a').attributes('data-inertia'), url).toBeUndefined();
            expect(wrapper.get('a').attributes('href')).toBe(url);
        }
    });

    it('draws them as plain links in the menu links and in the logos', () => {
        const links = mount(LinksBlock, {
            global: { plugins: [i18n] },
            props: {
                items: [
                    item({ id: '1', label: 'Panel', href: '/admin' }),
                    item({ id: '2', label: 'Normas', href: '/normas.pdf' }),
                ],
            },
        });
        const logos = mount(LogosBlock, {
            props: {
                logos: [
                    {
                        src: 'https://img.example/a.png',
                        alt: 'Panel',
                        url: '/admin/x',
                        width: null,
                        height: null,
                    },
                ],
            },
        });

        expect(links.findAll('a').map((link) => link.attributes('data-inertia'))).toEqual([
            undefined,
            undefined,
        ]);
        expect(logos.get('a').attributes('data-inertia')).toBeUndefined();
    });
});

describe('LogosBlock', () => {
    const logos = [
        {
            src: 'https://img.example/a.png',
            alt: 'Federación',
            url: 'https://example.com',
            width: 160,
            height: 40,
        },
        {
            src: 'https://img.example/b.png',
            alt: 'Club',
            url: '/nosotros',
            width: null,
            height: null,
        },
        { src: 'https://img.example/c.png', alt: 'Local', url: null, width: null, height: null },
    ];

    it('draws every logo with its alternative text, linked only when it has a link', () => {
        const wrapper = mount(LogosBlock, { props: { logos } });

        expect(wrapper.findAll('img').map((image) => image.attributes('alt'))).toEqual([
            'Federación',
            'Club',
            'Local',
        ]);
        const links = wrapper.findAll('a');
        expect(links.map((link) => link.attributes('href'))).toEqual([
            'https://example.com',
            '/nosotros',
        ]);
        expect(links[0]?.attributes('data-inertia')).toBeUndefined();
        expect(links[1]?.attributes('data-inertia')).toBeDefined();
        expect(wrapper.findAll('li')[2]?.find('a').exists()).toBe(false);
    });

    it('draws nothing without logos', () => {
        expect(
            mount(LogosBlock, { props: { logos: [] } })
                .find('ul')
                .exists()
        ).toBe(false);
    });
});
