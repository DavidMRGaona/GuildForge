import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import Show from '../Show.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import { setCloudinaryConfig } from '@/utils/cloudinary';
import type { PublicProfilePageProps } from '@/types/profilePage';
import type {
    ProfileHeader,
    ResolvedProfileSection,
    ResolvedProfileStat,
} from '@/types/profileSections';

vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'InertiaLink', props: ['href'], template: '<a :href="href"><slot /></a>' },
}));

vi.mock('@/components/layout/ProfileSection.vue', () => ({
    default: {
        props: ['section'],
        template: '<section :data-profile-section="section.key">{{ section.label }}</section>',
    },
}));

function section(key: string, region: 'main' | 'aside'): ResolvedProfileSection {
    return { key, module: null, component: 'components/X.vue', region, label: key, props: {} };
}

function props(
    header: Partial<ProfileHeader> = {},
    rest: Partial<Omit<PublicProfilePageProps, 'profile'>> & {
        stats?: ResolvedProfileStat[];
        main?: ResolvedProfileSection[];
        aside?: ResolvedProfileSection[];
    } = {}
): PublicProfilePageProps {
    return {
        profile: {
            header: {
                handle: 'ana-prueba',
                publicName: 'Ana María Prueba',
                avatarPublicId: null,
                bannerPublicId: null,
                memberSince: '2024-06-01T00:30:00+02:00',
                showsMembership: false,
                isOwner: false,
                ...header,
            },
            stats: rest.stats ?? [],
            sections: { main: rest.main ?? [], aside: rest.aside ?? [] },
        },
        notices: rest.notices ?? { choosePublicName: false, onlyOwner: false },
        editUrl: rest.editUrl ?? null,
        seo: {} as PublicProfilePageProps['seo'],
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

    it('shows the public name and the joining month of the display zone, never a handle', () => {
        const wrapper = mountPage(props());

        expect(wrapper.get('h1').text()).toBe('Ana María Prueba');
        expect(wrapper.text()).not.toContain('@ana-prueba');
        // 00:30 on 1 June in the display zone is still 31 May in UTC: the month is read from the text
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
        const wrapper = mountPage(
            props({ bannerPublicId: 'profiles/banners/b.jpg', avatarPublicId: 'avatars/a.jpg' })
        );

        const banner = wrapper.get('[data-testid="profile-band"] [data-testid="profile-banner"]');
        expect(banner.attributes('src')).toContain('w_1500,h_500,c_fill,g_auto');
        expect(banner.attributes('alt')).toBe('');
        expect(wrapper.get('h1').classes()).toContain('text-white');
        expect(wrapper.get('[data-testid="profile-avatar"] img').attributes('alt')).toBe('');
    });

    it('shows the membership badge only when the header says so', () => {
        expect(mountPage(props()).find('[data-testid="profile-membership"]').exists()).toBe(false);
        expect(
            mountPage(props({ showsMembership: true }))
                .get('[data-testid="profile-membership"]')
                .text()
        ).toBe('Socio');
    });

    it('lists the counters in order, linking only those with an address', () => {
        const wrapper = mountPage(
            props(
                {},
                {
                    stats: [
                        {
                            key: 'forum.posts',
                            label: 'Mensajes',
                            value: 1394,
                            url: '/foro?autor=ana',
                        },
                        { key: 'gt.tables', label: 'Mesas', value: 12, url: null },
                    ],
                }
            )
        );

        const items = wrapper.findAll('[data-testid="profile-stats"] li');
        expect(items.map((item) => item.text())).toEqual(['1394Mensajes', '12Mesas']);
        expect(items[0]?.get('a').attributes('href')).toBe('/foro?autor=ana');
        expect(items[1]?.find('a').exists()).toBe(false);
        expect(wrapper.find('[data-testid="profile-empty-visitor"]').exists()).toBe(false);
    });

    it('draws each region, «Sobre mí» first on narrow screens', () => {
        const wrapper = mountPage(
            props(
                {},
                {
                    main: [section('forum.latest-posts', 'main')],
                    aside: [section('core.about', 'aside'), section('gt.campaigns', 'aside')],
                }
            )
        );

        expect(
            wrapper
                .findAll('[data-region="main"] [data-profile-section]')
                .map((s) => s.attributes('data-profile-section'))
        ).toEqual(['forum.latest-posts']);
        const aside = wrapper.findAll('[data-region="aside"] > div');
        expect(aside.map((item) => item.classes())).toEqual([
            ['order-1', 'lg:order-none'],
            ['order-3', 'lg:order-none'],
        ]);
        expect(wrapper.get('[data-region="main"]').classes()).toContain('order-2');
        expect(wrapper.find('[data-region="empty"]').exists()).toBe(false);
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
        const wrapper = mountPage(props({ isOwner: true }, { editUrl: '/perfil' }));

        const empty = wrapper.get('[data-testid="profile-empty-owner"]');
        expect(empty.text()).toContain('Tu perfil todavía está vacío');
        expect(empty.get('a').attributes('href')).toBe('/perfil');
        expect(wrapper.find('[data-testid="profile-empty-visitor"]').exists()).toBe(false);
    });

    it('tells the owner of a hidden profile that only they see it', () => {
        const wrapper = mountPage(
            props(
                { isOwner: true },
                { editUrl: '/perfil', notices: { choosePublicName: false, onlyOwner: true } }
            )
        );

        const notice = wrapper.get('[data-testid="profile-owner-notice"]');
        expect(notice.text()).toContain('Solo tú ves este perfil.');
        expect(notice.find('a').exists()).toBe(false);
    });

    it('asks an owner without public name to choose one, never showing another name', () => {
        const wrapper = mountPage(
            props(
                { publicName: null, isOwner: true },
                { editUrl: '/perfil', notices: { choosePublicName: true, onlyOwner: false } }
            )
        );

        expect(wrapper.get('h1').text()).toBe('Sin nombre público');
        const notice = wrapper.get('[data-testid="profile-owner-notice"]');
        expect(notice.text()).toContain('Elige un nombre público');
        expect(notice.get('a').attributes('href')).toBe('/perfil');
        expect(wrapper.find('[data-testid="profile-avatar"] svg').exists()).toBe(true);
    });
});
