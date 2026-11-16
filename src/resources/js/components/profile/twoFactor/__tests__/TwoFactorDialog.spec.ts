import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type DOMWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import TwoFactorDialog from '../TwoFactorDialog.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { TwoFactorMode } from '@/types/twoFactor';

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
const notifications = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router: { visit: vi.fn() },
    usePage: (): typeof page => page,
}));

vi.mock('@/composables/useNotifications', () => ({
    useNotifications: (): typeof notifications => notifications,
}));

const fetchMock = vi.fn();

const SETUP = {
    secret: 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567',
    groupedSecret: 'ABCD EFGH IJKL MNOP QRST UVWX YZ23 4567',
    qrCodeSvg: '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"></svg>',
};
const CODES = Array.from({ length: 8 }, (_, index) => `abcd${index}-efgh${index}`);

function jsonResponse(body: unknown, status = 200): Response {
    return new Response(JSON.stringify(body), {
        status,
        headers: { 'Content-Type': 'application/json' },
    });
}

function mountDialog(mode: TwoFactorMode): ReturnType<typeof mount> {
    page.props = { appName: 'Example', auth: { user: { id: '1', mustVerifyEmail: false } } };
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(TwoFactorDialog, {
        props: { open: false, mode },
        global: { plugins: [i18n], stubs: { Teleport: true } },
    });
}

async function open(wrapper: ReturnType<typeof mount>): Promise<void> {
    await wrapper.setProps({ open: true });
    await flushPromises();
}

async function type(
    wrapper: ReturnType<typeof mount>,
    selector: string,
    value: string
): Promise<void> {
    await wrapper.get(selector).setValue(value);
}

async function submit(wrapper: ReturnType<typeof mount>): Promise<void> {
    await wrapper.get('form').trigger('submit');
    await flushPromises();
}

function lastCall(fetchMock: ReturnType<typeof vi.fn>): { url: string; init: RequestInit } {
    const [url, init] = fetchMock.mock.calls.at(-1) as [string, RequestInit];

    return { url, init };
}

function submitButton(
    wrapper: ReturnType<typeof mount>
): Omit<DOMWrapper<HTMLButtonElement>, 'exists'> {
    return wrapper.get<HTMLButtonElement>('button[type="submit"]');
}

/** Goes through the three first steps of turning it on, until the recovery codes are shown. */
async function reachCodes(wrapper: ReturnType<typeof mount>): Promise<void> {
    fetchMock.mockResolvedValueOnce(jsonResponse(SETUP));
    await type(wrapper, '#two-factor-password', 'secret-pass');
    await submit(wrapper);
    await submit(wrapper);
    fetchMock.mockResolvedValueOnce(jsonResponse({ recoveryCodes: CODES }));
    await type(wrapper, '#two-factor-code', '123456');
    await submit(wrapper);
}

describe('TwoFactorDialog', () => {
    beforeEach(() => {
        fetchMock.mockReset();
        vi.stubGlobal('fetch', fetchMock);
        // happy-dom has no modal dialogs
        HTMLDialogElement.prototype.showModal = vi.fn();
        HTMLDialogElement.prototype.close = vi.fn();
        notifications.success.mockReset();
        notifications.error.mockReset();
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('turns it on in four steps and shows the recovery codes last', async () => {
        const wrapper = mountDialog('enable');
        await open(wrapper);

        // Password, then QR and key
        expect(wrapper.text()).toContain('Paso 1 de 4');
        fetchMock.mockResolvedValueOnce(jsonResponse(SETUP));
        await type(wrapper, '#two-factor-password', 'secret-pass');
        await submit(wrapper);

        expect(lastCall(fetchMock).url).toBe('/perfil/doble-factor');
        expect(lastCall(fetchMock).init.method).toBe('POST');
        expect(JSON.parse(String(lastCall(fetchMock).init.body))).toEqual({
            current_password: 'secret-pass',
        });
        const qr = wrapper.get('img');
        expect(qr.attributes('src')).toMatch(/^data:image\/svg\+xml/);
        expect(qr.attributes('alt')).toContain('clave');
        expect(wrapper.get('[data-testid="two-factor-key"]').text()).toBe(SETUP.groupedSecret);
        // The password does not stay in memory once it has been used
        expect(wrapper.find('#two-factor-password').exists()).toBe(false);

        // The code from the app
        await submit(wrapper);
        const field = wrapper.get('#two-factor-code');
        expect(field.attributes('autocomplete')).toBe('one-time-code');
        expect(field.attributes('inputmode')).toBe('numeric');

        fetchMock.mockResolvedValueOnce(jsonResponse({ recoveryCodes: CODES }));
        await type(wrapper, '#two-factor-code', '123456');
        await submit(wrapper);

        expect(lastCall(fetchMock).url).toBe('/perfil/doble-factor/confirmar');
        expect(JSON.parse(String(lastCall(fetchMock).init.body))).toEqual({ code: '123456' });
        // The secret is gone and the codes are on screen
        expect(wrapper.find('img').exists()).toBe(false);
        expect(wrapper.findAll('[data-testid="recovery-codes"] li')).toHaveLength(8);

        // It cannot be finished before saying the codes were kept
        expect(submitButton(wrapper).attributes('disabled')).toBeDefined();
        await wrapper.get('input[type="checkbox"]').setValue(true);
        expect(submitButton(wrapper).attributes('disabled')).toBeUndefined();
        await submit(wrapper);

        expect(wrapper.emitted('done')).toEqual([['enable']]);
        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('shows a wrong password under its field and stays on the step', async () => {
        const wrapper = mountDialog('enable');
        await open(wrapper);
        fetchMock.mockResolvedValueOnce(
            jsonResponse({ errors: { current_password: ['La contraseña es incorrecta.'] } }, 422)
        );

        await type(wrapper, '#two-factor-password', 'wrong');
        await submit(wrapper);

        expect(wrapper.get('#two-factor-password-error').text()).toBe(
            'La contraseña es incorrecta.'
        );
        expect(wrapper.get('#two-factor-password').attributes('aria-invalid')).toBe('true');
        expect(wrapper.find('img').exists()).toBe(false);
    });

    it('shows a wrong code under its field and keeps the secret for another try', async () => {
        const wrapper = mountDialog('enable');
        await open(wrapper);
        fetchMock.mockResolvedValueOnce(jsonResponse(SETUP));
        await type(wrapper, '#two-factor-password', 'secret-pass');
        await submit(wrapper);
        await submit(wrapper);

        fetchMock.mockResolvedValueOnce(
            jsonResponse({ errors: { code: ['El código no es correcto.'] } }, 422)
        );
        await type(wrapper, '#two-factor-code', '000000');
        await submit(wrapper);

        expect(wrapper.get('#two-factor-code-error').text()).toBe('El código no es correcto.');
        expect(wrapper.emitted('done')).toBeUndefined();
    });

    it('does not close with Escape while the recovery codes are not marked as saved', async () => {
        const wrapper = mountDialog('recoveryCodes');
        await open(wrapper);
        fetchMock.mockResolvedValueOnce(jsonResponse({ recoveryCodes: CODES }));
        await type(wrapper, '#two-factor-password', 'secret-pass');
        await type(wrapper, '#two-factor-code', '123456');
        await submit(wrapper);

        await wrapper.get('dialog').trigger('cancel');
        expect(wrapper.emitted('update:open')).toBeUndefined();

        await wrapper.get('input[type="checkbox"]').setValue(true);
        await wrapper.get('dialog').trigger('cancel');
        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('shows the dialog again when the browser closes it on the codes step without a cancel', async () => {
        const wrapper = mountDialog('enable');
        await open(wrapper);
        await reachCodes(wrapper);
        const showModal = HTMLDialogElement.prototype.showModal as ReturnType<typeof vi.fn>;
        showModal.mockClear();

        // A second Escape (Chromium close watchers) or Android's back gesture: `close`, no `cancel`
        await wrapper.get('dialog').trigger('close');

        expect(showModal).toHaveBeenCalledTimes(1);
        expect(wrapper.emitted('update:open')).toBeUndefined();
        expect(wrapper.emitted('done')).toBeUndefined();
        // The codes are still there, so the page can still be told that it was turned on
        expect(wrapper.findAll('[data-testid="recovery-codes"] li')).toHaveLength(8);
    });

    it('closes for good, tells the page and empties the codes once they are marked as saved', async () => {
        const wrapper = mountDialog('enable');
        await open(wrapper);
        await reachCodes(wrapper);
        await wrapper.get('input[type="checkbox"]').setValue(true);

        await wrapper.get('dialog').trigger('close');

        expect(wrapper.emitted('update:open')).toEqual([[false]]);
        expect(wrapper.emitted('done')).toEqual([['enable']]);
        expect(wrapper.find('[data-testid="recovery-codes"]').exists()).toBe(false);
    });

    it('does not tell the page twice when it closes by itself after finishing', async () => {
        const wrapper = mountDialog('enable');
        await open(wrapper);
        await reachCodes(wrapper);
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await submit(wrapper);
        await wrapper.setProps({ open: false });

        await wrapper.get('dialog').trigger('close');

        expect(wrapper.emitted('done')).toEqual([['enable']]);
        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('closes on an early step without telling the page, since nothing changed', async () => {
        const wrapper = mountDialog('enable');
        await open(wrapper);

        await wrapper.get('dialog').trigger('close');

        expect(wrapper.emitted('update:open')).toEqual([[false]]);
        expect(wrapper.emitted('done')).toBeUndefined();
    });

    it('empties the recovery codes from memory when the dialog is closed', async () => {
        const wrapper = mountDialog('enable');
        await open(wrapper);
        await reachCodes(wrapper);
        await wrapper.get('input[type="checkbox"]').setValue(true);
        await submit(wrapper);

        await wrapper.setProps({ open: false });
        expect(wrapper.find('[data-testid="recovery-codes"]').exists()).toBe(false);

        // Opened again, it starts over: no step shows the old codes
        await open(wrapper);
        expect(wrapper.find('[data-testid="recovery-codes"]').exists()).toBe(false);
        expect(wrapper.find('#two-factor-password').exists()).toBe(true);
    });

    it('asks for the password and a code to make new codes, accepting a recovery code', async () => {
        const wrapper = mountDialog('recoveryCodes');
        await open(wrapper);
        fetchMock.mockResolvedValueOnce(jsonResponse({ recoveryCodes: CODES }));

        await type(wrapper, '#two-factor-password', 'secret-pass');
        await type(wrapper, '#two-factor-code', 'abcde-fghij');
        await submit(wrapper);

        expect(lastCall(fetchMock).url).toBe('/perfil/doble-factor/codigos');
        expect(lastCall(fetchMock).init.method).toBe('POST');
        expect(JSON.parse(String(lastCall(fetchMock).init.body))).toEqual({
            current_password: 'secret-pass',
            code: 'abcde-fghij',
        });
        expect(wrapper.findAll('[data-testid="recovery-codes"] li')).toHaveLength(8);
    });

    it('turns it off with a DELETE carrying the password and a code', async () => {
        const wrapper = mountDialog('disable');
        await open(wrapper);
        fetchMock.mockResolvedValueOnce(new Response(null, { status: 204 }));

        await type(wrapper, '#two-factor-password', 'secret-pass');
        await type(wrapper, '#two-factor-code', '123456');
        await submit(wrapper);

        expect(lastCall(fetchMock).url).toBe('/perfil/doble-factor');
        expect(lastCall(fetchMock).init.method).toBe('DELETE');
        expect(wrapper.emitted('done')).toEqual([['disable']]);
        expect(wrapper.emitted('update:open')).toEqual([[false]]);
    });

    it('changes the app proving the current factor first and without showing recovery codes', async () => {
        const wrapper = mountDialog('changeApp');
        await open(wrapper);
        expect(wrapper.text()).toContain('Paso 1 de 3');
        fetchMock.mockResolvedValueOnce(jsonResponse(SETUP));
        await type(wrapper, '#two-factor-password', 'secret-pass');
        await type(wrapper, '#two-factor-code', '123456');
        await submit(wrapper);

        // Password and a code of the app in use start it
        expect(lastCall(fetchMock).url).toBe('/perfil/doble-factor/app');
        expect(JSON.parse(String(lastCall(fetchMock).init.body))).toEqual({
            current_password: 'secret-pass',
            code: '123456',
        });
        expect(wrapper.find('img').exists()).toBe(true);

        // The code of the new app, typed in a field that starts empty
        await submit(wrapper);
        expect((wrapper.get('#two-factor-code').element as HTMLInputElement).value).toBe('');
        fetchMock.mockResolvedValueOnce(new Response(null, { status: 204 }));
        await type(wrapper, '#two-factor-code', '654321');
        await submit(wrapper);

        expect(lastCall(fetchMock).url).toBe('/perfil/doble-factor/app/confirmar');
        expect(JSON.parse(String(lastCall(fetchMock).init.body))).toEqual({ code: '654321' });
        expect(wrapper.emitted('done')).toEqual([['changeApp']]);
    });

    it('shows a text for a refusal that is not about a field', async () => {
        const wrapper = mountDialog('disable');
        await open(wrapper);
        fetchMock.mockResolvedValueOnce(
            jsonResponse({ message: 'Tu cuenta no puede desactivar el doble factor.' }, 403)
        );

        await type(wrapper, '#two-factor-password', 'secret-pass');
        await type(wrapper, '#two-factor-code', '123456');
        await submit(wrapper);

        expect(wrapper.get('[role="alert"]').text()).toBe(
            'Tu cuenta no puede desactivar el doble factor.'
        );
        expect(wrapper.emitted('done')).toBeUndefined();
    });
});
