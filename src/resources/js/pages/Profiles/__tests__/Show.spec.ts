import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, reactive, type VNode } from 'vue';
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import Show from '../Show.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import { useNotifications } from '@/composables/useNotifications';
import { setCloudinaryConfig } from '@/utils/cloudinary';
import type { ProfileEditor, ProfileEditorForm } from '@/types/profileEditor';
import type { PublicProfilePageProps } from '@/types/profilePage';
import type {
    ProfileHeader,
    ResolvedProfileSection,
    ResolvedProfileStat,
} from '@/types/profileSections';

const page = reactive<{ props: { flash?: { success?: string } } }>({ props: {} });
vi.mock('@inertiajs/vue3', () => ({
    Link: { name: 'InertiaLink', props: ['href'], template: '<a :href="href"><slot /></a>' },
    usePage: (): typeof page => page,
}));

vi.mock('@/components/layout/ProfileSection.vue', () => ({
    default: {
        props: ['section'],
        template: '<section :data-profile-section="section.key">{{ section.label }}</section>',
    },
}));

/** The panel's own behaviour has its own tests: here it only sends a draft, or closes */
let sendPreview: ((form: ProfileEditorForm) => void) | null = null;
vi.mock('@/components/profileEditor/ProfileEditorPanel.vue', () => ({
    default: defineComponent({
        props: { editor: { type: Object, required: true } },
        emits: ['preview', 'close'],
        setup(_, { emit }) {
            sendPreview = (form: ProfileEditorForm): void => emit('preview', form);
            return (): VNode =>
                h('div', { 'data-testid': 'profile-editor' }, [
                    h('button', { 'data-testid': 'close-editor', onClick: () => emit('close') }),
                ]);
        },
    }),
}));

// A page left mounted would keep answering the flash messages of later tests
enableAutoUnmount(afterEach);

function editor(): ProfileEditor {
    return {
        handle: 'ana-prueba',
        visibility: 'members',
        bio: '',
        bannerPublicId: null,
        showMembership: false,
        canShowMembership: true,
        nextHandleChangeAt: null,
        sections: [
            {
                key: 'core.about',
                label: 'Sobre mí',
                description: '',
                enabled: true,
                region: 'aside',
                module: null,
                component: 'components/profileSections/AboutSection.vue',
            },
        ],
        stats: [{ key: 'forum.posts', label: 'Mensajes', description: '', enabled: true }],
        limits: {
            bio: 500,
            handleMin: 3,
            handleMax: 30,
            handleChangeDays: 30,
            bannerKilobytes: 5120,
            bannerTypes: ['jpg', 'png', 'webp'],
        },
        urls: {
            update: '/miembros/ana-prueba',
            availability: '/miembros/ana-prueba/disponibilidad',
            banner: '/miembros/ana-prueba/banner',
        },
    };
}

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
        notices: rest.notices ?? { choosePublicName: false, onlyOwner: false, verifyEmail: false },
        verifyEmailUrl: rest.verifyEmailUrl ?? null,
        accountUrl: rest.accountUrl ?? null,
        editor: rest.editor ?? null,
        seo: {} as PublicProfilePageProps['seo'],
    };
}

function mountPage(pageProps: PublicProfilePageProps): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(Show, { attachTo: document.body, props: pageProps, global: { plugins: [i18n] } });
}

describe('Profiles/Show', () => {
    beforeEach(() => {
        setCloudinaryConfig({ cloudName: 'demo', prefix: 'site' });
        page.props = {};
        useNotifications().clearAll();
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

        const region = wrapper.get('[data-testid="profile-stats"]');
        // A row that can scroll: a named region the keyboard can reach
        expect(region.attributes('role')).toBe('region');
        expect(region.attributes('tabindex')).toBe('0');
        expect(region.attributes('aria-label')).toBe('Estadísticas');
        const items = wrapper.findAll('[data-testid="profile-stats"] li');
        expect(items.map((item) => item.text())).toEqual(['1394Mensajes', '12Mesas']);
        expect(items[0]?.get('a').attributes('href')).toBe('/foro?autor=ana');
        expect(items[1]?.find('a').exists()).toBe(false);
        expect(wrapper.find('[data-testid="profile-empty-visitor"]').exists()).toBe(false);
    });

    it('puts the sections in the DOM in the order a narrow screen shows them', () => {
        const wrapper = mountPage(
            props(
                {},
                {
                    main: [section('forum.latest-posts', 'main')],
                    aside: [section('core.about', 'aside'), section('gt.campaigns', 'aside')],
                }
            )
        );

        // Focus and reading follow what is seen: «Sobre mí», the main column, the rest of the side one
        expect(
            wrapper
                .findAll('[data-profile-section]')
                .map((s) => s.attributes('data-profile-section'))
        ).toEqual(['core.about', 'forum.latest-posts', 'gt.campaigns']);
        const [lead, other] = wrapper.findAll('[data-region="aside"]');
        expect(lead?.classes()).toEqual(
            expect.arrayContaining(['lg:col-start-3', 'lg:row-start-1'])
        );
        expect(other?.classes()).toEqual(
            expect.arrayContaining(['lg:col-start-3', 'lg:row-start-2'])
        );
        expect(wrapper.get('[data-region="main"]').classes()).toEqual(
            expect.arrayContaining([
                'lg:col-span-2',
                'lg:col-start-1',
                'lg:row-span-2',
                'lg:row-start-1',
            ])
        );
        expect(wrapper.find('[data-region="empty"]').exists()).toBe(false);
    });

    it('starts the side column at the top when there is no «Sobre mí»', () => {
        const wrapper = mountPage(
            props(
                {},
                {
                    main: [section('forum.latest-posts', 'main')],
                    aside: [section('gt.campaigns', 'aside')],
                }
            )
        );

        expect(wrapper.get('[data-region="aside"]').classes()).toContain('lg:row-start-1');
    });

    it('keeps one narrow column when only one region has sections', () => {
        const wrapper = mountPage(props({}, { aside: [section('core.about', 'aside')] }));

        const regions = wrapper.get('[data-region="aside"]').element.parentElement;
        expect(regions?.classList.contains('max-w-3xl')).toBe(true);
        expect(wrapper.get('[data-region="aside"]').classes()).not.toContain('lg:col-start-3');
    });

    it('gives a visitor the quiet empty state and no owner notice', () => {
        const wrapper = mountPage(props());

        expect(wrapper.get('[data-testid="profile-empty-visitor"]').text()).toBe(
            'Todavía no hay nada que mostrar.'
        );
        expect(wrapper.find('[data-testid="profile-empty-owner"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="profile-owner-notice"]').exists()).toBe(false);
    });

    it('invites the owner to edit an empty profile, from the editor', async () => {
        const wrapper = mountPage(
            props({ isOwner: true }, { accountUrl: '/perfil', editor: editor() })
        );

        const empty = wrapper.get('[data-testid="profile-empty-owner"]');
        expect(empty.text()).toContain('Tu perfil todavía está vacío');
        expect(wrapper.find('[data-testid="profile-empty-visitor"]').exists()).toBe(false);

        await empty.get('[data-testid="profile-empty-edit"]').trigger('click');
        expect(wrapper.find('[data-testid="profile-editor"]').exists()).toBe(true);
    });

    it('shows what a save answered as a notice, each time it answers', async () => {
        page.props = { flash: { success: 'Perfil guardado.' } };
        const wrapper = mountPage(props({ isOwner: true }, { editor: editor() }));
        expect(useNotifications().notifications.value.map((n) => n.message)).toEqual([
            'Perfil guardado.',
        ]);

        page.props = { flash: { success: 'Perfil guardado.' } };
        await wrapper.vm.$nextTick();
        expect(useNotifications().notifications.value).toHaveLength(2);
    });

    it('asks an owner who must verify the email to do it, with no editor', () => {
        const wrapper = mountPage(
            props(
                { isOwner: true },
                {
                    notices: { choosePublicName: false, onlyOwner: false, verifyEmail: true },
                    verifyEmailUrl: '/verificar-email',
                }
            )
        );

        const notice = wrapper.get('[data-testid="profile-verify-notice"]');
        expect(notice.text()).toContain(
            'Verifica tu correo electrónico para poder editar tu perfil.'
        );
        expect(notice.get('a').attributes('href')).toBe('/verificar-email');
        expect(wrapper.find('[data-testid="profile-edit"]').exists()).toBe(false);
    });

    it('gives the focus back to the button that opened the editor', async () => {
        const wrapper = mountPage(props({ isOwner: true }, { editor: editor() }));

        await wrapper.get('[data-testid="profile-empty-edit"]').trigger('click');
        await wrapper.get('[data-testid="close-editor"]').trigger('click');
        await flushPromises();
        expect(document.activeElement).toBe(
            wrapper.get('[data-testid="profile-empty-edit"]').element
        );

        await wrapper.get('[data-testid="profile-edit"]').trigger('click');
        await wrapper.get('[data-testid="close-editor"]').trigger('click');
        await flushPromises();
        expect(document.activeElement).toBe(wrapper.get('[data-testid="profile-edit"]').element);
    });

    it('offers the editor only to the owner', () => {
        expect(mountPage(props()).find('[data-testid="profile-edit"]').exists()).toBe(false);
        expect(
            mountPage(props({ isOwner: true }, { editor: editor() }))
                .find('[data-testid="profile-edit"]')
                .exists()
        ).toBe(true);
    });

    it('turns the page into the preview of the editor while it is open, and back when it closes', async () => {
        const wrapper = mountPage(
            props(
                { isOwner: true },
                {
                    editor: editor(),
                    stats: [{ key: 'forum.posts', label: 'Mensajes', value: 4, url: null }],
                }
            )
        );

        await wrapper.get('[data-testid="profile-edit"]').trigger('click');
        expect(wrapper.get('[data-testid="profile-edit"]').attributes('aria-expanded')).toBe(
            'true'
        );

        sendPreview?.({
            handle: 'ana-prueba',
            visibility: 'members',
            bio: 'Juego a rol',
            show_membership: true,
            sections: [{ key: 'core.about', enabled: true }],
            stats: [{ key: 'forum.posts', enabled: false }],
        });
        await flushPromises();

        expect(wrapper.find('[data-profile-section="core.about"]').exists()).toBe(true);
        expect(wrapper.find('[data-testid="profile-stats"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="profile-membership"]').exists()).toBe(true);

        await wrapper.get('[data-testid="close-editor"]').trigger('click');
        expect(wrapper.find('[data-testid="profile-editor"]').exists()).toBe(false);
        expect(wrapper.find('[data-profile-section="core.about"]').exists()).toBe(false);
        expect(wrapper.find('[data-testid="profile-stats"]').exists()).toBe(true);
    });

    it('tells the owner of a hidden profile that only they see it', () => {
        const wrapper = mountPage(
            props(
                { isOwner: true },
                {
                    accountUrl: '/perfil',
                    notices: { choosePublicName: false, onlyOwner: true, verifyEmail: false },
                }
            )
        );

        const notice = wrapper.get('[data-testid="profile-owner-notice"]');
        expect(notice.text()).toContain('Solo tú ves este perfil.');
        expect(notice.find('a').exists()).toBe(false);
    });

    it('gives the owner of a hidden profile without public name both notices', () => {
        const wrapper = mountPage(
            props(
                { publicName: null, isOwner: true },
                {
                    accountUrl: '/perfil',
                    notices: { choosePublicName: true, onlyOwner: true, verifyEmail: false },
                }
            )
        );

        const notice = wrapper.get('[data-testid="profile-owner-notice"]');
        expect(notice.text()).toContain('Solo tú ves este perfil.');
        expect(notice.text()).toContain('Elige un nombre público');
    });

    it('asks an owner without public name to choose one, never showing another name', () => {
        const wrapper = mountPage(
            props(
                { publicName: null, isOwner: true },
                {
                    accountUrl: '/perfil',
                    notices: { choosePublicName: true, onlyOwner: false, verifyEmail: false },
                }
            )
        );

        expect(wrapper.get('h1').text()).toBe('Sin nombre público');
        const notice = wrapper.get('[data-testid="profile-owner-notice"]');
        expect(notice.text()).toContain('Elige un nombre público');
        expect(notice.get('a').attributes('href')).toBe('/perfil');
        expect(wrapper.find('[data-testid="profile-avatar"] svg').exists()).toBe(true);
    });
});
