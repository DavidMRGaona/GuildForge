import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileMailTab from '../ProfileMailTab.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { MailPreferenceSection } from '@/types/mailPreferences';

type PutOptions = {
    onStart?: () => void;
    onFinish?: () => void;
    onError?: (errors: Record<string, string>) => void;
};

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
const router = vi.hoisted(() => ({ put: vi.fn(), visit: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router,
    usePage: (): typeof page => page,
}));

const SECTIONS: MailPreferenceSection[] = [
    {
        key: 'Cuenta',
        label: 'Cuenta',
        categories: [
            {
                key: 'core.account',
                label: 'Correos de tu cuenta',
                description: 'Verificación, contraseña y bienvenida.',
                mandatory: true,
                mandatoryReason: 'Siempre se envía: confirma algo que has hecho tú.',
                enabled: true,
            },
        ],
    },
    {
        key: 'Partidas',
        label: 'Partidas',
        categories: [
            {
                key: 'game-tables.reminders',
                label: 'Recordatorios',
                description: 'Antes de que empiece una partida.',
                mandatory: false,
                mandatoryReason: null,
                enabled: true,
            },
            {
                key: 'game-tables.news',
                label: 'Novedades',
                description: 'Partidas nuevas.',
                mandatory: false,
                mandatoryReason: null,
                enabled: false,
            },
        ],
    },
];

function mountTab(mustVerifyEmail = false): VueWrapper {
    page.props = { appName: 'Mi sitio', auth: { user: { id: '1', mustVerifyEmail } } };
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ProfileMailTab, {
        props: { sections: SECTIONS },
        global: { plugins: [i18n], stubs: { HelpLink: true } },
    });
}

function switchFor(wrapper: VueWrapper, key: string): ReturnType<VueWrapper['find']> {
    return wrapper.find(`[data-category="${key}"] [role="switch"]`);
}

describe('ProfileMailTab', () => {
    beforeEach(() => {
        router.put.mockReset();
    });

    it('draws a labelled switch per category, grouped by section', () => {
        const wrapper = mountTab();

        expect(wrapper.findAll('section')).toHaveLength(2);
        expect(wrapper.findAll('[role="switch"]')).toHaveLength(3);

        const reminders = switchFor(wrapper, 'game-tables.reminders');
        expect(reminders.attributes('aria-checked')).toBe('true');
        const labelId = reminders.attributes('aria-labelledby') ?? '';
        expect(wrapper.find(`#${labelId}`).text()).toBe('Recordatorios');
        expect(switchFor(wrapper, 'game-tables.news').attributes('aria-checked')).toBe('false');
    });

    it('shows a mandatory category locked, with its reason read by the switch', () => {
        const wrapper = mountTab();

        const locked = switchFor(wrapper, 'core.account');
        expect(locked.attributes('aria-checked')).toBe('true');
        expect(locked.attributes('aria-disabled')).toBe('true');
        expect(locked.attributes('disabled')).toBeUndefined();

        const describedBy = (locked.attributes('aria-describedby') ?? '').split(' ');
        const reasonText = describedBy.map((id) => wrapper.find(`#${id}`).text()).join(' ');
        expect(reasonText).toContain('Siempre se envía: confirma algo que has hecho tú.');
    });

    it('does not change a locked switch and keeps the save button off', async () => {
        const wrapper = mountTab();

        await switchFor(wrapper, 'core.account').trigger('click');

        expect(switchFor(wrapper, 'core.account').attributes('aria-checked')).toBe('true');
        expect(wrapper.find('button[type="submit"]').attributes('disabled')).toBeDefined();
    });

    it('sends only the categories that changed', async () => {
        const wrapper = mountTab();

        await switchFor(wrapper, 'game-tables.reminders').trigger('click');
        await switchFor(wrapper, 'game-tables.news').trigger('click');
        expect(wrapper.text()).toContain('Tienes cambios sin guardar.');
        await wrapper.find('form').trigger('submit');

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, data] = router.put.mock.calls[0] as [string, unknown];
        expect(url).toBe('/perfil/correos');
        expect(data).toEqual({
            preferences: { 'game-tables.reminders': false, 'game-tables.news': true },
        });
    });

    it('sends nothing when a choice is put back as it was', async () => {
        const wrapper = mountTab();

        await switchFor(wrapper, 'game-tables.reminders').trigger('click');
        await switchFor(wrapper, 'game-tables.reminders').trigger('click');
        await wrapper.find('form').trigger('submit');

        expect(router.put).not.toHaveBeenCalled();
    });

    it('shows the error the server gives for a category, tied to its switch', async () => {
        const wrapper = mountTab();
        await switchFor(wrapper, 'game-tables.reminders').trigger('click');
        await wrapper.find('form').trigger('submit');

        const options = (router.put.mock.calls[0] as [string, unknown, PutOptions])[2];
        options.onError?.({ 'preferences.game-tables.reminders': 'No se puede cambiar.' });
        await wrapper.vm.$nextTick();

        const reminders = switchFor(wrapper, 'game-tables.reminders');
        const describedBy = (reminders.attributes('aria-describedby') ?? '').split(' ');
        expect(describedBy.map((id) => wrapper.find(`#${id}`).text())).toContain(
            'No se puede cambiar.'
        );
    });

    it('shows a general alert when the failure is not about a category', async () => {
        const wrapper = mountTab();
        await switchFor(wrapper, 'game-tables.news').trigger('click');
        await wrapper.find('form').trigger('submit');

        const options = (router.put.mock.calls[0] as [string, unknown, PutOptions])[2];
        options.onError?.({ preferences: 'Obligatorio.' });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[role="alert"]').text()).toContain('No se han podido guardar');
    });

    it('locks everything for an account that must verify its email', async () => {
        const wrapper = mountTab(true);

        const reminders = switchFor(wrapper, 'game-tables.reminders');
        expect(reminders.attributes('aria-disabled')).toBe('true');
        await reminders.trigger('click');
        expect(reminders.attributes('aria-checked')).toBe('true');
        expect(wrapper.text()).toContain('verifica tu email');
    });

    it('leaves a slot for the pause block', () => {
        const wrapper = mountTab();

        expect(wrapper.find('[data-slot="mail-pause"]').exists()).toBe(true);
    });
});
