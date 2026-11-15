import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import Show from '../Show.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import { setCloudinaryConfig } from '@/utils/cloudinary';
import type { PublicProfilePageProps } from '@/types/profilePage';

vi.mock('@inertiajs/vue3', () => ({
    Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

function props(overrides: Partial<PublicProfilePageProps> = {}): PublicProfilePageProps {
    return {
        profile: {
            handle: 'ana-prueba',
            url: 'http://localhost/miembros/ana-prueba',
            publicName: 'Ana María Prueba',
            avatarPublicId: null,
            bannerPublicId: null,
            membershipBadge: false,
            memberSince: '2024-06',
        },
        isOwner: false,
        notices: { choosePublicName: false, onlyOwner: false },
        editUrl: null,
        seo: {} as PublicProfilePageProps['seo'],
        ...overrides,
    };
}

function mountPage(pageProps: PublicProfilePageProps): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(Show, { props: pageProps, global: { plugins: [i18n] } });
}

describe('Profiles/Show', () => {
    beforeEach(() => {
        setCloudinaryConfig({ cloudName: 'demo', prefix: 'site' });
    });

    it('shows the public name and the joining month, never the address as a handle', () => {
        const wrapper = mountPage(props());

        expect(wrapper.get('h1').text()).toBe('Ana María Prueba');
        expect(wrapper.text()).not.toContain('@ana-prueba');
        expect(wrapper.get('time').attributes('datetime')).toBe('2024-06');
        expect(wrapper.get('time').text()).toBe('Miembro desde junio de 2024');
    });

    it('keeps the plain band without a banner and draws the initials without a photo', () => {
        const wrapper = mountPage(props());

        expect(wrapper.find('[data-testid="profile-banner"]').exists()).toBe(false);
        expect(wrapper.get('[data-testid="profile-band"]').classes()).toContain('bg-muted');
        expect(wrapper.get('h1').classes()).toContain('text-base-primary');
        expect(wrapper.get('[data-testid="profile-avatar"]').text()).toBe('AP');
    });

    it('puts the banner behind the band, cropped at delivery, with the name in white', () => {
        const base = props();
        const wrapper = mountPage(
            props({
                profile: {
                    ...base.profile,
                    bannerPublicId: 'profiles/banners/b.jpg',
                    avatarPublicId: 'avatars/a.jpg',
                },
            })
        );

        const banner = wrapper.get('[data-testid="profile-band"] [data-testid="profile-banner"]');
        expect(banner.attributes('src')).toContain('w_1500,h_500,c_fill,g_auto');
        expect(banner.attributes('alt')).toBe('');
        expect(wrapper.get('h1').classes()).toContain('text-white');
        expect(wrapper.get('[data-testid="profile-avatar"] img').attributes('alt')).toBe('');
    });

    it('shows the membership badge only when the server says so', () => {
        const base = props();

        expect(mountPage(base).find('[data-testid="profile-membership"]').exists()).toBe(false);
        expect(
            mountPage(props({ profile: { ...base.profile, membershipBadge: true } }))
                .get('[data-testid="profile-membership"]')
                .text()
        ).toBe('Socio');
    });

    it('gives a visitor the quiet empty state and no owner notice', () => {
        const wrapper = mountPage(props());

        expect(wrapper.get('[data-testid="profile-empty-visitor"]').text()).toBe(
            'Todavía no hay nada que mostrar.'
        );
        expect(wrapper.find('[data-testid="profile-empty-owner"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="profile-owner-notice"]').exists()).toBe(false);
    });

    it('invites the owner to edit an empty profile', () => {
        const wrapper = mountPage(props({ isOwner: true, editUrl: '/perfil' }));

        const empty = wrapper.get('[data-testid="profile-empty-owner"]');
        expect(empty.text()).toContain('Tu perfil todavía está vacío');
        expect(empty.get('a').attributes('href')).toBe('/perfil');
        expect(wrapper.find('[data-testid="profile-empty-visitor"]').exists()).toBe(false);
    });

    it('tells the owner of a hidden profile that only they see it', () => {
        const wrapper = mountPage(
            props({
                isOwner: true,
                editUrl: '/perfil',
                notices: { choosePublicName: false, onlyOwner: true },
            })
        );

        const notice = wrapper.get('[data-testid="profile-owner-notice"]');
        expect(notice.text()).toContain('Solo tú ves este perfil.');
        expect(notice.find('a').exists()).toBe(false);
    });

    it('asks an owner without public name to choose one, never showing another name', () => {
        const base = props();
        const wrapper = mountPage(
            props({
                profile: { ...base.profile, publicName: null },
                isOwner: true,
                editUrl: '/perfil',
                notices: { choosePublicName: true, onlyOwner: false },
            })
        );

        expect(wrapper.get('h1').text()).toBe('Sin nombre público');
        const notice = wrapper.get('[data-testid="profile-owner-notice"]');
        expect(notice.text()).toContain('Elige un nombre público');
        expect(notice.get('a').attributes('href')).toBe('/perfil');
        expect(wrapper.find('[data-testid="profile-avatar"] svg').exists()).toBe(true);
    });
});
