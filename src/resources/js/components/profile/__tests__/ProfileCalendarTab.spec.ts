import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type DOMWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileCalendarTab from '../ProfileCalendarTab.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { CalendarFeedStatus } from '@/types/calendarFeed';

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
const notifications = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn() },
    usePage: (): typeof page => page,
}));

vi.mock('@/composables/useNotifications', () => ({
    useNotifications: (): typeof notifications => notifications,
}));

const FEED_URL =
    'https://example.test/calendario/personal/abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQ.ics';
const WEBCAL_URL =
    'webcal://example.test/calendario/personal/abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQ.ics';

function mountTab(
    calendarFeed: CalendarFeedStatus,
    mustVerifyEmail = false
): ReturnType<typeof mount> {
    page.props = { auth: { user: { id: '1', mustVerifyEmail } } };
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ProfileCalendarTab, {
        props: { calendarFeed },
        global: {
            plugins: [i18n],
            stubs: { HelpLink: true, Teleport: true },
        },
    });
}

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

function buttonWithText(
    wrapper: ReturnType<typeof mount>,
    text: string
): DOMWrapper<HTMLButtonElement> {
    const button = wrapper.findAll('button').find((candidate) => candidate.text() === text);
    if (!button) {
        throw new Error(`No button «${text}»`);
    }

    return button;
}

describe('ProfileCalendarTab', () => {
    const fetchMock = vi.fn<typeof fetch>();

    beforeEach(() => {
        vi.stubGlobal('fetch', fetchMock);
        // happy-dom has no showModal on <dialog>
        HTMLDialogElement.prototype.showModal = vi.fn();
        HTMLDialogElement.prototype.close = vi.fn();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        fetchMock.mockReset();
        notifications.success.mockReset();
        notifications.error.mockReset();
    });

    it('offers to create an address when there is none', () => {
        const wrapper = mountTab({ available: true, issuedAt: null });

        expect(wrapper.text()).toContain('Crear dirección de suscripción');
        expect(wrapper.find('[data-testid="calendar-feed-addresses"]').exists()).toBe(false);
    });

    it('shows both private addresses with copy buttons right after creating one', async () => {
        fetchMock.mockResolvedValue(
            jsonResponse({
                feedUrl: FEED_URL,
                webcalUrl: WEBCAL_URL,
                issuedAt: '2026-11-07T19:30:00+01:00',
            })
        );
        const wrapper = mountTab({ available: true, issuedAt: null });

        await buttonWithText(wrapper, 'Crear dirección de suscripción').trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/perfil/calendario',
            expect.objectContaining({ method: 'POST' })
        );
        const values = wrapper
            .findAll('input[readonly]')
            .map((input) => (input.element as HTMLInputElement).value);
        expect(values).toEqual([WEBCAL_URL, FEED_URL]);
        expect(
            wrapper.findAll('button').filter((button) => button.text() === 'Copiar')
        ).toHaveLength(2);
        expect(wrapper.text()).toContain('Esta dirección es privada');
        expect(wrapper.text()).toContain('Solo se muestra ahora');
        expect(notifications.success).toHaveBeenCalledWith('Dirección creada');
    });

    it('copies an address to the clipboard', async () => {
        const writeText = vi.fn().mockResolvedValue(undefined);
        Object.defineProperty(navigator, 'clipboard', { value: { writeText }, configurable: true });
        fetchMock.mockResolvedValue(
            jsonResponse({ feedUrl: FEED_URL, webcalUrl: WEBCAL_URL, issuedAt: null })
        );
        const wrapper = mountTab({ available: true, issuedAt: null });
        await buttonWithText(wrapper, 'Crear dirección de suscripción').trigger('click');
        await flushPromises();

        await wrapper
            .findAll('button')
            .filter((button) => button.text() === 'Copiar')[1]
            ?.trigger('click');
        await flushPromises();

        expect(writeText).toHaveBeenCalledWith(FEED_URL);
        expect(wrapper.text()).toContain('Copiada');
    });

    it('says an earlier address exists without showing it', () => {
        const wrapper = mountTab({ available: true, issuedAt: '2026-10-01T10:00:00+02:00' });

        expect(wrapper.text()).toContain(
            'Tienes una dirección activa desde el 1 de octubre de 2026'
        );
        expect(wrapper.find('input[readonly]').exists()).toBe(false);
        expect(wrapper.text()).toContain('Crear una dirección nueva');
        expect(wrapper.text()).toContain('Revocar dirección');
    });

    it('revokes the address after confirming', async () => {
        fetchMock.mockResolvedValue(new Response(null, { status: 204 }));
        const wrapper = mountTab({ available: true, issuedAt: '2026-10-01T10:00:00+02:00' });

        await buttonWithText(wrapper, 'Revocar dirección').trigger('click');
        expect(fetchMock).not.toHaveBeenCalled();
        await buttonWithText(wrapper, 'Revocar').trigger('click');
        await flushPromises();

        expect(fetchMock).toHaveBeenCalledWith(
            '/perfil/calendario',
            expect.objectContaining({ method: 'DELETE' })
        );
        expect(wrapper.text()).toContain('Crear dirección de suscripción');
        expect(notifications.success).toHaveBeenCalledWith('Dirección revocada');
    });

    it('tells the person when the request fails', async () => {
        fetchMock.mockResolvedValue(jsonResponse({ message: 'Server Error' }, 500));
        vi.spyOn(console, 'error').mockImplementation(() => undefined);
        const wrapper = mountTab({ available: true, issuedAt: null });

        await buttonWithText(wrapper, 'Crear dirección de suscripción').trigger('click');
        await flushPromises();

        expect(notifications.error).toHaveBeenCalledWith(
            'No se ha podido completar la acción. Inténtalo de nuevo.'
        );
        expect(wrapper.find('input[readonly]').exists()).toBe(false);
    });

    it('cannot create an address while the email must be verified', async () => {
        const wrapper = mountTab({ available: true, issuedAt: null }, true);

        const button = buttonWithText(wrapper, 'Crear dirección de suscripción');
        expect(button.attributes('disabled')).toBeDefined();
        expect(wrapper.text()).toContain(
            'Para crear la dirección de tu calendario, verifica tu email.'
        );
        await button.trigger('click');

        expect(fetchMock).not.toHaveBeenCalled();
    });
});
