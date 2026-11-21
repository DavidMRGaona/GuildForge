import { beforeEach, describe, expect, it, vi } from 'vitest';
import { flushPromises, mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileMailTab from '../ProfileMailTab.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { MailPreferenceSection, PushDevice } from '@/types/mailPreferences';

type PutOptions = { onError?: (errors: Record<string, string>) => void };

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
const router = vi.hoisted(() => ({
    put: vi.fn(),
    delete: vi.fn(),
    visit: vi.fn(),
    reload: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    router,
    usePage: (): typeof page => page,
}));

vi.mock('@/utils/webPush', () => ({
    thisDevicePushState: vi.fn().mockResolvedValue('available'),
    subscribeThisDevice: vi.fn(),
    syncThisDevice: vi.fn(),
    removeDevice: vi.fn(),
    sendTestNotice: vi.fn(),
}));

/** Replies (suggested for push), likes (site only, off on the site), moderation (always). */
const SECTIONS: MailPreferenceSection[] = [
    {
        key: 'Foro',
        label: 'Foro',
        icon: 'message-square',
        categories: [
            {
                key: 'forum.replies',
                label: 'Respuestas',
                description: 'En los temas que sigues.',
                mandatory: false,
                mandatoryReason: null,
                enabled: true,
                digestible: false,
                delivery: 'immediate',
                mail: true,
                site: true,
                siteEnabled: true,
                pushEnabled: false,
                pushSuggested: true,
            },
            {
                key: 'forum.likes',
                label: 'Me gusta',
                description: 'En tus mensajes.',
                mandatory: false,
                mandatoryReason: null,
                enabled: true,
                digestible: false,
                delivery: 'immediate',
                mail: false,
                site: true,
                siteEnabled: false,
                pushEnabled: false,
            },
            {
                key: 'forum.digest',
                label: 'Resumen',
                description: 'Solo por correo.',
                mandatory: false,
                mandatoryReason: null,
                enabled: true,
                digestible: false,
                delivery: 'immediate',
            },
            {
                key: 'forum.moderation',
                label: 'Moderación',
                description: 'Lo que la moderación hizo con tus mensajes.',
                mandatory: true,
                mandatoryReason: 'Cambia lo que escribiste.',
                enabled: true,
                digestible: false,
                delivery: 'immediate',
                mail: true,
                site: true,
                siteEnabled: true,
            },
        ],
    },
];

const DEVICE: PushDevice = {
    id: 'device-1',
    browser: 'chrome',
    platform: 'android',
    current: true,
    createdAt: '2026-11-21T10:00:00+01:00',
    lastUsedAt: null,
};

function mountTab(options: { push?: boolean; devices?: PushDevice[] } = {}): VueWrapper {
    page.props = {
        appName: 'Mi sitio',
        auth: { user: { id: 'user-a', mustVerifyEmail: false } },
        webPush: options.push === false ? null : { publicKey: 'key' },
    };
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ProfileMailTab, {
        props: {
            sections: SECTIONS,
            pause: { active: false, until: null, lastDay: null },
            pushDevices: options.devices ?? [],
        },
        global: { plugins: [i18n], stubs: { HelpLink: true } },
    });
}

function pushSwitch(wrapper: VueWrapper, key: string): ReturnType<VueWrapper['find']> {
    return wrapper.find(`[data-category="${key}"] [data-channel="push"]`);
}

describe('ProfileMailTab — push notifications (core#804)', () => {
    beforeEach(() => {
        window.localStorage.clear();
        router.put.mockReset();
    });

    it('has no push switch nor devices block while push is off on the site', () => {
        const wrapper = mountTab({ push: false });

        expect(wrapper.find('[data-testid="push-devices"]').exists()).toBe(false);
        expect(wrapper.find('[data-channel="push"]').exists()).toBe(false);
    });

    it('gives each optional category with site notices a push switch, off by default', () => {
        const wrapper = mountTab();

        expect(wrapper.find('[data-testid="push-devices"]').exists()).toBe(true);
        expect(pushSwitch(wrapper, 'forum.replies').attributes('aria-checked')).toBe('false');
        expect(pushSwitch(wrapper, 'forum.likes').exists()).toBe(true);
        expect(pushSwitch(wrapper, 'forum.digest').exists()).toBe(false);
        expect(
            wrapper.find('[data-category="forum.replies"] [data-testid="push-suggested"]').exists()
        ).toBe(true);
        expect(
            wrapper.find('[data-category="forum.likes"] [data-testid="push-suggested"]').exists()
        ).toBe(false);
    });

    it('locks the push switch while the bell does not get the category', async () => {
        const wrapper = mountTab();

        const likes = pushSwitch(wrapper, 'forum.likes');
        expect(likes.attributes('aria-disabled')).toBe('true');
        expect(
            wrapper.find('[data-category="forum.likes"] [data-testid="push-needs-site"]').exists()
        ).toBe(true);

        await wrapper.find('[data-category="forum.likes"] [data-channel="site"]').trigger('click');
        expect(pushSwitch(wrapper, 'forum.likes').attributes('aria-disabled')).toBe('false');
    });

    it('saves only the push switches that changed, under push', async () => {
        const wrapper = mountTab();

        await pushSwitch(wrapper, 'forum.replies').trigger('click');
        await wrapper.find('form').trigger('submit');

        expect(router.put).toHaveBeenCalledWith(
            '/perfil/correos',
            { push: { 'forum.replies': true } },
            expect.anything()
        );
    });

    it('shows a refused push switch next to it', async () => {
        router.put.mockImplementation((_url: string, _data: unknown, options: PutOptions) => {
            options.onError?.({
                'push.forum.replies': 'Esa categoría de correo no se puede cambiar.',
            });
        });
        const wrapper = mountTab();

        await pushSwitch(wrapper, 'forum.replies').trigger('click');
        await wrapper.find('form').trigger('submit');

        expect(
            wrapper
                .find('[data-category="forum.replies"] [data-testid="push-channel-error"]')
                .text()
        ).toBe('Esa categoría de correo no se puede cambiar.');
    });

    it('turns on the suggested ones, with the web switch push needs, to be saved with the rest', async () => {
        const wrapper = mountTab({ devices: [DEVICE] });
        await flushPromises();

        await wrapper.find('[data-action="enable-suggested"]').trigger('click');

        expect(pushSwitch(wrapper, 'forum.replies').attributes('aria-checked')).toBe('true');
        expect(router.put).not.toHaveBeenCalled();
        expect(wrapper.find('[data-action="enable-suggested"]').exists()).toBe(false);

        await wrapper.find('form').trigger('submit');
        expect(router.put).toHaveBeenCalledWith(
            '/perfil/correos',
            { push: { 'forum.replies': true } },
            expect.anything()
        );
    });

    it('offers the suggested ones only once a device receives notices', async () => {
        const wrapper = mountTab();
        await flushPromises();

        expect(wrapper.find('[data-action="enable-suggested"]').exists()).toBe(false);
    });

    it('keeps unsaved choices when only the devices list is reloaded', async () => {
        const wrapper = mountTab({ devices: [DEVICE] });

        await pushSwitch(wrapper, 'forum.replies').trigger('click');
        await wrapper.setProps({ pushDevices: [], sections: structuredClone(SECTIONS) });

        expect(pushSwitch(wrapper, 'forum.replies').attributes('aria-checked')).toBe('true');
    });
});
