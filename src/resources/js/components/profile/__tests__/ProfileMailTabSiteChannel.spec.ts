import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileMailTab from '../ProfileMailTab.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { MailPreferenceSection } from '@/types/mailPreferences';

type PutOptions = { onError?: (errors: Record<string, string>) => void };

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
const router = vi.hoisted(() => ({ put: vi.fn(), delete: vi.fn(), visit: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({
    router,
    usePage: (): typeof page => page,
}));

/** A forum section: replies by mail and on the site, likes only on the site, moderation always. */
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
            },
            {
                key: 'forum.topics',
                label: 'Temas nuevos',
                description: 'En las categorías que sigues.',
                mandatory: false,
                mandatoryReason: null,
                enabled: true,
                digestible: true,
                delivery: 'digest',
                mail: true,
                site: true,
                siteEnabled: true,
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

function mountTab(): VueWrapper {
    page.props = { appName: 'Mi sitio', auth: { user: { id: '1', mustVerifyEmail: false } } };
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ProfileMailTab, {
        props: { sections: SECTIONS, pause: { active: false, until: null, lastDay: null } },
        global: { plugins: [i18n], stubs: { HelpLink: true } },
    });
}

function channelSwitch(
    wrapper: VueWrapper,
    key: string,
    channel: 'site' | 'mail'
): ReturnType<VueWrapper['find']> {
    return wrapper.find(`[data-category="${key}"] [data-channel="${channel}"]`);
}

function labelOf(wrapper: VueWrapper, control: ReturnType<VueWrapper['find']>): string {
    return (control.attributes('aria-labelledby') ?? '')
        .split(' ')
        .map((id) => wrapper.find(`#${id}`).text())
        .join(', ');
}

describe('ProfileMailTab — site notices (core#491)', () => {
    beforeEach(() => {
        window.localStorage.clear();
        router.put.mockReset();
    });

    it('gives a category with site notices a web switch and a mail switch, each named by both', () => {
        const wrapper = mountTab();

        const site = channelSwitch(wrapper, 'forum.replies', 'site');
        const mail = channelSwitch(wrapper, 'forum.replies', 'mail');

        expect(site.attributes('aria-checked')).toBe('true');
        expect(mail.attributes('aria-checked')).toBe('true');
        expect(labelOf(wrapper, site)).toBe('Respuestas, En la web');
        expect(labelOf(wrapper, mail)).toBe('Respuestas, Por correo');
    });

    it('shows only the web switch for a category that is never mailed', () => {
        const wrapper = mountTab();

        expect(channelSwitch(wrapper, 'forum.likes', 'site').attributes('aria-checked')).toBe(
            'false'
        );
        expect(channelSwitch(wrapper, 'forum.likes', 'mail').exists()).toBe(false);
        expect(wrapper.find('[data-category="forum.likes"]').text()).not.toContain('Por correo');
    });

    it('keeps the three deliveries of a digestible category next to its web switch', () => {
        const wrapper = mountTab();

        expect(wrapper.findAll('[data-category="forum.topics"] input[type="radio"]')).toHaveLength(
            3
        );
        expect(channelSwitch(wrapper, 'forum.topics', 'site').exists()).toBe(true);
    });

    it('says a mandatory category is also shown on the site', () => {
        const wrapper = mountTab();

        expect(wrapper.find('[data-mandatory-category="forum.moderation"]').text()).toContain(
            'También se avisa en la web.'
        );
    });

    it('sends only the changed web switches, apart from the mail choices', async () => {
        const wrapper = mountTab();

        await channelSwitch(wrapper, 'forum.replies', 'site').trigger('click');
        await channelSwitch(wrapper, 'forum.likes', 'site').trigger('click');
        await wrapper.find('form').trigger('submit');

        const [url, data] = router.put.mock.calls[0] as [string, unknown];
        expect(url).toBe('/perfil/correos');
        expect(data).toEqual({ site: { 'forum.replies': false, 'forum.likes': true } });
    });

    it('sends both maps when both channels changed', async () => {
        const wrapper = mountTab();

        await channelSwitch(wrapper, 'forum.replies', 'mail').trigger('click');
        await channelSwitch(wrapper, 'forum.replies', 'site').trigger('click');
        await wrapper.find('form').trigger('submit');

        const [, data] = router.put.mock.calls[0] as [string, unknown];
        expect(data).toEqual({
            preferences: { 'forum.replies': false },
            site: { 'forum.replies': false },
        });
    });

    it('counts a category as on while either channel is on', async () => {
        const wrapper = mountTab();
        const summary = (): string => wrapper.get('[data-testid="mail-section-summary"]').text();

        // Replies (both), likes (off on the site, no mail) and topics (digest): 2 of 3
        expect(summary()).toBe('2 de 3 activados');

        await channelSwitch(wrapper, 'forum.replies', 'mail').trigger('click');
        expect(summary()).toBe('2 de 3 activados');

        await channelSwitch(wrapper, 'forum.replies', 'site').trigger('click');
        expect(summary()).toBe('1 de 3 activados');
    });

    it('shows a refused web switch under its category', async () => {
        router.put.mockImplementation((_url: string, _data: unknown, options: PutOptions) => {
            options.onError?.({ 'site.forum.likes': 'No puedes cambiar este aviso.' });
        });
        const wrapper = mountTab();

        await channelSwitch(wrapper, 'forum.likes', 'site').trigger('click');
        await wrapper.find('form').trigger('submit');

        expect(wrapper.find('[data-category="forum.likes"]').text()).toContain(
            'No puedes cambiar este aviso.'
        );
    });
});
