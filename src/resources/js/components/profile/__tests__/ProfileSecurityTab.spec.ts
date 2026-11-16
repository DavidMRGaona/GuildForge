import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type DOMWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileSecurityTab from '../ProfileSecurityTab.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { SecurityProps } from '@/types/twoFactor';

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
const reload = vi.hoisted(() => vi.fn());
const notifications = vi.hoisted(() => ({ success: vi.fn(), error: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
    router: { visit: vi.fn(), reload },
    usePage: (): typeof page => page,
    useForm: <T extends object>(
        data: T
    ): T & { processing: boolean; errors: Record<string, string>; put: () => void } => ({
        ...data,
        processing: false,
        errors: {},
        put: vi.fn(),
    }),
}));

vi.mock('@/composables/useNotifications', () => ({
    useNotifications: (): typeof notifications => notifications,
}));

const OFF: SecurityProps = {
    twoFactor: { active: false, since: null, remainingCodes: 0, canDisable: true, requiredBy: [] },
};

function on(overrides: Partial<SecurityProps['twoFactor']> = {}): SecurityProps {
    return {
        twoFactor: {
            active: true,
            since: '2026-11-10T12:00:00+01:00',
            remainingCodes: 8,
            canDisable: true,
            requiredBy: [],
            ...overrides,
        },
    };
}

function mountTab(
    security: SecurityProps,
    mustVerifyEmail = false,
    attach = false
): ReturnType<typeof mount> {
    page.props = { auth: { user: { id: '1', mustVerifyEmail } } };
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ProfileSecurityTab, {
        props: { security },
        global: { plugins: [i18n], stubs: { TwoFactorDialog: true } },
        ...(attach ? { attachTo: document.body } : {}),
    });
}

function button(wrapper: ReturnType<typeof mount>, text: string): DOMWrapper<HTMLButtonElement> {
    const found = wrapper.findAll('button').find((candidate) => candidate.text() === text);
    if (!found) {
        throw new Error(`No button «${text}»`);
    }

    return found;
}

describe('ProfileSecurityTab', () => {
    beforeEach(() => {
        reload.mockReset();
        notifications.success.mockReset();
    });

    it('keeps the password form of the account tab', () => {
        const wrapper = mountTab(OFF);

        expect(wrapper.find('input#current_password').exists()).toBe(true);
        expect(wrapper.find('input#new_password').exists()).toBe(true);
    });

    it('offers to turn it on while it is off', async () => {
        const wrapper = mountTab(OFF);

        expect(wrapper.get('[data-testid="two-factor-status"]').text()).toBe('Desactivado');
        expect(wrapper.find('[data-testid="two-factor-codes-left"]').exists()).toBe(false);

        await button(wrapper, 'Activar doble factor').trigger('click');

        const dialog = wrapper.getComponent({ name: 'TwoFactorDialog' });
        expect(dialog.props('open')).toBe(true);
        expect(dialog.props('mode')).toBe('enable');
    });

    it('shows since when it is on and how many codes are left', () => {
        const wrapper = mountTab(on({ remainingCodes: 5 }));

        expect(wrapper.get('[data-testid="two-factor-status"]').text()).toBe('Activado');
        expect(wrapper.text()).toContain('Activo desde el 10 de noviembre de 2026.');
        expect(wrapper.get('[data-testid="two-factor-codes-left"]').text()).toBe(
            'Te quedan 5 códigos de recuperación sin usar.'
        );
        expect(wrapper.find('[data-testid="two-factor-few-codes"]').exists()).toBe(false);
    });

    it.each([2, 1, 0])('warns that %i codes are few and suggests making new ones', (left) => {
        const wrapper = mountTab(on({ remainingCodes: left }));

        expect(wrapper.find('[data-testid="two-factor-few-codes"]').exists()).toBe(true);
    });

    it('opens the dialog in the mode of the button pressed', async () => {
        const wrapper = mountTab(on());
        const dialog = wrapper.getComponent({ name: 'TwoFactorDialog' });

        await button(wrapper, 'Generar códigos nuevos').trigger('click');
        expect(dialog.props('mode')).toBe('recoveryCodes');
        await button(wrapper, 'Cambiar de app').trigger('click');
        expect(dialog.props('mode')).toBe('changeApp');
        await button(wrapper, 'Desactivar').trigger('click');
        expect(dialog.props('mode')).toBe('disable');
        expect(dialog.props('open')).toBe(true);
    });

    it('names the role that requires it while it is off', () => {
        const wrapper = mountTab({ twoFactor: { ...OFF.twoFactor, requiredBy: ['Tesorería'] } });

        expect(wrapper.find('[data-testid="two-factor-required-by"]').text()).toBe(
            'Tu rol Tesorería exige doble factor: no funciona hasta que lo actives.'
        );
    });

    it('names every role that requires it, as a list', () => {
        const wrapper = mountTab({
            twoFactor: { ...OFF.twoFactor, requiredBy: ['Secretaría', 'Tesorería'] },
        });

        expect(wrapper.find('[data-testid="two-factor-required-by"]').text()).toBe(
            'Tus roles Secretaría y Tesorería exigen doble factor: no funcionan hasta que lo actives.'
        );
    });

    it('says nothing about roles when none requires it, or once it is on', () => {
        expect(mountTab(OFF).find('[data-testid="two-factor-required-by"]').exists()).toBe(false);
        expect(
            mountTab(on({ requiredBy: ['Tesorería'], canDisable: false }))
                .find('[data-testid="two-factor-required-by"]')
                .exists()
        ).toBe(false);
    });

    it('hides «Desactivar» and says why when the server says it cannot be turned off', () => {
        const wrapper = mountTab(on({ canDisable: false }));

        expect(
            wrapper.findAll('button').some((candidate) => candidate.text() === 'Desactivar')
        ).toBe(false);
        expect(wrapper.find('[data-testid="two-factor-cannot-disable"]').exists()).toBe(true);
        // Changing the app stays possible
        expect(button(wrapper, 'Cambiar de app').exists()).toBe(true);
    });

    it('reloads only what the tab shows once a change is done', async () => {
        const wrapper = mountTab(OFF);

        wrapper.getComponent({ name: 'TwoFactorDialog' }).vm.$emit('done', 'enable');
        await wrapper.vm.$nextTick();

        expect(notifications.success).toHaveBeenCalledWith('Doble factor activado.');
        expect(reload).toHaveBeenCalledWith(expect.objectContaining({ only: ['security'] }));
    });

    it('moves the focus to the status of the card after turning it on, since its button is gone', async () => {
        const wrapper = mountTab(OFF, false, true);

        wrapper.getComponent({ name: 'TwoFactorDialog' }).vm.$emit('done', 'enable');
        await wrapper.vm.$nextTick();
        const options = reload.mock.calls[0]?.[0] as { onFinish: () => void };
        options.onFinish();
        await wrapper.vm.$nextTick();

        expect(document.activeElement).toBe(
            wrapper.get('[data-testid="two-factor-status"]').element
        );
        wrapper.unmount();
    });

    it('keeps the focus on the button that opened the dialog for the other changes', async () => {
        const wrapper = mountTab(on(), false, true);
        const regenerate = button(wrapper, 'Generar códigos nuevos');
        (regenerate.element as HTMLElement).focus();

        wrapper.getComponent({ name: 'TwoFactorDialog' }).vm.$emit('done', 'recoveryCodes');
        await wrapper.vm.$nextTick();
        (reload.mock.calls[0]?.[0] as { onFinish: () => void }).onFinish();
        await wrapper.vm.$nextTick();

        expect(document.activeElement).toBe(regenerate.element);
        wrapper.unmount();
    });

    it('blocks the two-factor buttons for an account that must verify its email', async () => {
        const wrapper = mountTab(OFF, true);

        expect(button(wrapper, 'Activar doble factor').attributes('disabled')).toBeDefined();
        await button(wrapper, 'Activar doble factor').trigger('click');
        expect(wrapper.getComponent({ name: 'TwoFactorDialog' }).props('open')).toBe(false);
    });
});
