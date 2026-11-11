import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import type { Component } from 'vue';
import { createPinia } from 'pinia';
import { createI18n } from 'vue-i18n';
import ArticlesIndex from '../Articles/Index.vue';
import EventsIndex from '../Events/Index.vue';
import GalleryIndex from '../Gallery/Index.vue';
import EventsViewToggle from '@/components/events/EventsViewToggle.vue';
import en from '@/locales/en';
import es from '@/locales/es';

vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn() },
    usePage: (): { url: string; props: Record<string, unknown> } => ({ url: '/', props: {} }),
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

const emptyPage = {
    data: [],
    meta: { currentPage: 1, lastPage: 1, perPage: 12, total: 0 },
    links: { first: null, last: null, prev: null, next: null },
};

function mountPage(page: Component, props: Record<string, unknown>): ReturnType<typeof mount> {
    return mount(page, {
        props,
        global: {
            plugins: [
                createPinia(),
                createI18n({ legacy: false, locale: 'es', messages: { es, en } }),
            ],
            stubs: { HelpLink: true, EventList: true, ArticleList: true, GalleryGrid: true },
        },
    });
}

// The lists open with the same band: title, its help and a subtitle of their own
describe('list page bands', () => {
    it.each([
        [
            'events',
            EventsIndex,
            { events: emptyPage },
            'core.events.find-events',
            'Jornadas, quedadas y actividades de la asociación',
        ],
        [
            'articles',
            ArticlesIndex,
            { articles: emptyPage },
            'core.content.read-articles',
            'Noticias y artículos de la asociación',
        ],
        [
            'galleries',
            GalleryIndex,
            { galleries: emptyPage },
            'core.content.browse-galleries',
            'Galería de fotos de la asociación',
        ],
    ] as const)(
        'the %s list has the shared band with its help guide',
        (_name, page, props, guide, subtitle) => {
            const band = mountPage(page, props).get('[data-testid="page-band"]');

            expect(band.classes()).toContain('page-band');
            expect(band.get('h1 + help-link-stub').attributes('guide')).toBe(guide);
            expect(band.text()).toContain(subtitle);
        }
    );

    it('the event list offers the calendar from its band', () => {
        const band = mountPage(EventsIndex, { events: emptyPage }).get('[data-testid="page-band"]');

        expect(band.findComponent(EventsViewToggle).props('current')).toBe('list');
    });
});
