import { afterEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import type { ComputedRef } from 'vue';
import Show from '../Show.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { AddToCalendarLinks, Event } from '@/types/models';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: (): { url: string; props: Record<string, unknown> } => ({ url: '/', props: {} }),
}));

// Whether the side column fits the window is a measurement: each test decides it
const asideFits = vi.hoisted(() => ({ value: true }));
vi.mock('@/composables/useFitsViewportHeight', async () => {
    const { computed } = await import('vue');

    return {
        useFitsViewportHeight: (): ComputedRef<boolean> => computed(() => asideFits.value),
    };
});

const calendarLinks: AddToCalendarLinks = {
    google: 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=Partida',
    outlook: 'https://outlook.live.com/calendar/0/deeplink/compose?subject=Partida',
    ics: '/calendario/event/0198a1b2-0000-7000-8000-000000000001.ics',
};

function event(overrides: Partial<Event> = {}): Event {
    const start = new Date(Date.now() + 7 * 24 * 60 * 60 * 1000);
    const end = new Date(start.getTime() + 2 * 60 * 60 * 1000);

    return {
        id: '0198a1b2-0000-7000-8000-000000000001',
        title: 'Partida de ejemplo',
        slug: 'partida-de-ejemplo',
        description: '<p>Descripción del evento</p>',
        startDate: start.toISOString(),
        endDate: end.toISOString(),
        location: 'Local de ejemplo',
        imagePublicId: null,
        memberPrice: null,
        nonMemberPrice: null,
        isPublished: true,
        createdAt: start.toISOString(),
        updatedAt: start.toISOString(),
        downloadLinks: [],
        tags: [],
        ...overrides,
    };
}

let wrapper: VueWrapper | null = null;

function mountPage(props: { event: Event; calendarLinks?: AddToCalendarLinks | null }): VueWrapper {
    wrapper = mount(Show, {
        props,
        global: {
            plugins: [createI18n({ legacy: false, locale: 'es', messages: { es, en } })],
            stubs: {
                ModuleSlot: { template: '<div data-module-slot />' },
                AddToCalendarMenu: { template: '<div data-add-to-calendar />' },
            },
        },
    });

    return wrapper;
}

afterEach(() => {
    wrapper?.unmount();
    wrapper = null;
});

describe('Events/Show', () => {
    it('shows a free event as one more detail next to the date and place', () => {
        const page = mountPage({ event: event() });

        const details = page.get('[data-testid="event-details"]');
        expect(details.text()).toContain('Local de ejemplo');
        expect(details.text()).toContain('Gratuito');
    });

    it('shows member and non-member prices among the details', () => {
        const page = mountPage({ event: event({ memberPrice: 5, nonMemberPrice: 8 }) });

        // Intl separates the amount and the symbol with a no-break space
        const details = page.get('[data-testid="event-details"]').text().replace(/\s/g, ' ');
        expect(details).toContain('Precio socios: 5,00 €');
        expect(details).toContain('Precio no socios: 8,00 €');
        expect(details).not.toContain('Gratuito');
    });

    it('offers adding to the calendar after the module actions', () => {
        const page = mountPage({ event: event(), calendarLinks });

        const html = page.html();
        const slotAt = html.indexOf('data-module-slot');
        const calendarAt = html.indexOf('data-add-to-calendar');
        expect(slotAt).toBeGreaterThan(-1);
        expect(calendarAt).toBeGreaterThan(slotAt);
    });

    it('stretches the calendar button to the side column, like the actions above it', () => {
        const page = mountPage({ event: event(), calendarLinks });

        // The stub declares no props: what the page passes shows up as attributes
        const calendar = page.get('[data-add-to-calendar]');
        expect(calendar.attributes('block')).toBeDefined();
    });

    it('does not offer adding a past event to the calendar', () => {
        const start = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
        const page = mountPage({
            event: event({
                startDate: start.toISOString(),
                endDate: new Date(start.getTime() + 60 * 60 * 1000).toISOString(),
            }),
            calendarLinks,
        });

        expect(page.find('[data-add-to-calendar]').exists()).toBe(false);
    });

    it('links back to every event with a grammatical label', () => {
        const page = mountPage({ event: event() });

        expect(page.text()).toContain('Ver todos los eventos');
    });

    describe('side column', () => {
        const withDownloads = {
            downloadLinks: [
                { label: 'Reglamento', url: 'https://example.com/r.pdf', description: '' },
            ],
        };

        it('holds the module actions, the calendar and the downloads, not the description', () => {
            const page = mountPage({ event: event(withDownloads), calendarLinks });

            const aside = page.get('[data-testid="event-aside"]');
            expect(aside.find('[data-module-slot]').exists()).toBe(true);
            expect(aside.find('[data-add-to-calendar]').exists()).toBe(true);
            expect(aside.text()).toContain('Reglamento');
            expect(aside.text()).not.toContain('Descripción del evento');
        });

        it('comes after the description, so a phone reads what the event is before signing up', () => {
            const page = mountPage({ event: event() });

            const html = page.html();
            const descriptionAt = html.indexOf('Descripción del evento');
            expect(descriptionAt).toBeGreaterThan(-1);
            expect(html.indexOf('data-testid="event-aside"')).toBeGreaterThan(descriptionAt);
        });

        it('stays in view while scrolling when it fits the window', () => {
            asideFits.value = true;
            const page = mountPage({ event: event() });

            expect(page.get('[data-testid="event-aside"]').classes()).toContain('lg:sticky');
        });

        it('scrolls with the page when it is taller than the window', () => {
            asideFits.value = false;
            const page = mountPage({ event: event() });

            expect(page.get('[data-testid="event-aside"]').classes()).not.toContain('lg:sticky');
            asideFits.value = true;
        });
    });
});
