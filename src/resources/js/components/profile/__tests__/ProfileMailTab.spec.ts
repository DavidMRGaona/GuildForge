import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileMailTab from '../ProfileMailTab.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { MailPause, MailPreferenceSection } from '@/types/mailPreferences';

type PutOptions = {
    onStart?: () => void;
    onFinish?: () => void;
    onError?: (errors: Record<string, string>) => void;
};

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
const router = vi.hoisted(() => ({ put: vi.fn(), delete: vi.fn(), visit: vi.fn() }));

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

const NO_PAUSE: MailPause = { active: false, until: null, lastDay: null };

function mountTab(mustVerifyEmail = false, pause: MailPause = NO_PAUSE): VueWrapper {
    page.props = { appName: 'Mi sitio', auth: { user: { id: '1', mustVerifyEmail } } };
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ProfileMailTab, {
        props: { sections: SECTIONS, pause },
        global: { plugins: [i18n], stubs: { HelpLink: true } },
    });
}

function switchFor(wrapper: VueWrapper, key: string): ReturnType<VueWrapper['find']> {
    return wrapper.find(`[data-category="${key}"] [role="switch"]`);
}

describe('ProfileMailTab', () => {
    beforeEach(() => {
        router.put.mockReset();
        router.delete.mockReset();
    });

    it('draws a labelled switch per category, grouped by section', () => {
        const wrapper = mountTab();

        expect(
            wrapper.find('[data-testid="mail-preference-sections"]').findAll('section')
        ).toHaveLength(2);
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

    it('keeps the pause block above the categories', () => {
        const wrapper = mountTab();

        const slot = wrapper.find('[data-slot="mail-pause"]');
        expect(slot.exists()).toBe(true);
        expect(slot.find('[data-testid="mail-pause-form"]').exists()).toBe(true);
        expect(slot.element.compareDocumentPosition(wrapper.find('section').element)).toBe(
            Node.DOCUMENT_POSITION_FOLLOWING
        );
    });

    it('offers a week, a month or until the person resumes, with the week chosen', () => {
        const wrapper = mountTab();

        const radios = wrapper.findAll('input[name="mail-pause-duration"]');
        expect(radios.map((radio) => (radio.element as HTMLInputElement).value)).toEqual([
            'week',
            'month',
            'indefinite',
        ]);
        expect((radios[0]?.element as HTMLInputElement).checked).toBe(true);
        expect(wrapper.find('fieldset legend').text()).toBe('Pausar los correos optativos');
    });

    it('draws the duration choices like the site radios, not the browser ones', async () => {
        const wrapper = mountTab();
        const dots = (): string[] =>
            wrapper
                .findAll('[data-testid="mail-pause-form"] label > span[aria-hidden="true"] > span')
                .map((dot) => dot.classes().join(' '));

        const radios = wrapper.findAll('input[name="mail-pause-duration"]');
        radios.forEach((radio) => expect(radio.classes()).toContain('sr-only'));
        expect(dots().map((classes) => classes.includes('scale-100'))).toEqual([
            true,
            false,
            false,
        ]);

        await radios[1]?.setValue(true);

        expect(dots().map((classes) => classes.includes('scale-100'))).toEqual([
            false,
            true,
            false,
        ]);
    });

    it('keeps the switch knob centred inside the bordered track', () => {
        const wrapper = mountTab();

        const knob = wrapper.find('[role="switch"] span[aria-hidden="true"] > span');
        expect(knob.classes()).toEqual(expect.arrayContaining(['left-px', 'top-px', 'h-5', 'w-5']));
    });

    it('pauses with the chosen duration and does not touch the category choices', async () => {
        const wrapper = mountTab();

        await wrapper.find('input[value="month"]').setValue(true);
        await wrapper.find('[data-testid="mail-pause-form"] button').trigger('click');

        expect(router.put).toHaveBeenCalledTimes(1);
        const [url, data] = router.put.mock.calls[0] as [string, unknown];
        expect(url).toBe('/perfil/correos/pausa');
        expect(data).toEqual({ duration: 'month' });
    });

    it('says until when it is paused and resumes with one button', async () => {
        const wrapper = mountTab(false, {
            active: true,
            until: '2026-12-13T00:00:00+01:00',
            lastDay: '2026-12-12',
        });

        const active = wrapper.find('[data-testid="mail-pause-active"]');
        expect(active.find('[role="status"]').text()).toBe('En pausa hasta el 12 de diciembre');
        expect(wrapper.find('[data-testid="mail-pause-form"]').exists()).toBe(false);

        await active.find('button').trigger('click');
        expect(router.delete).toHaveBeenCalledTimes(1);
        expect((router.delete.mock.calls[0] as [string])[0]).toBe('/perfil/correos/pausa');
    });

    it('says an indefinite pause lasts until the person resumes', () => {
        const wrapper = mountTab(false, { active: true, until: null, lastDay: null });

        expect(wrapper.find('[role="status"]').text()).toBe('En pausa hasta que los reactives');
    });

    it('shows an alert when the pause cannot be changed', async () => {
        const wrapper = mountTab();
        await wrapper.find('[data-testid="mail-pause-form"] button').trigger('click');

        const options = (router.put.mock.calls[0] as [string, unknown, PutOptions])[2];
        options.onError?.({ duration: 'Inválido.' });
        await wrapper.vm.$nextTick();

        expect(wrapper.find('[data-testid="mail-pause-form"] [role="alert"]').text()).toContain(
            'No se ha podido cambiar la pausa'
        );
    });

    it('lists the muted things below the categories, outside the form', () => {
        page.props = { appName: 'Mi sitio', auth: { user: { id: '1', mustVerifyEmail: false } } };
        const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });
        const wrapper = mount(ProfileMailTab, {
            props: {
                sections: SECTIONS,
                pause: NO_PAUSE,
                mutedSubjects: [
                    {
                        type: 'game-tables.campaign',
                        id: 'c-1',
                        label: 'La tumba',
                        typeLabel: 'Campaña',
                        mutedAt: '2026-12-01T10:00:00+01:00',
                    },
                ],
            },
            global: { plugins: [i18n], stubs: { HelpLink: true } },
        });

        const block = wrapper.find('[data-testid="muted-mail-subjects"]');
        expect(block.text()).toContain('La tumba');
        expect(wrapper.find('form [data-testid="muted-mail-subjects"]').exists()).toBe(false);
    });

    it('does not pause an account that must verify its email', async () => {
        const wrapper = mountTab(true);

        await wrapper.find('[data-testid="mail-pause-form"] button').trigger('click');

        expect(router.put).not.toHaveBeenCalled();
        expect(
            wrapper.find('[data-testid="mail-pause-form"] button').attributes('disabled')
        ).toBeDefined();
    });
});
