import type { ProfileEditor } from '@/types/profileEditor';

export function editorFixture(overrides: Partial<ProfileEditor> = {}): ProfileEditor {
    return {
        handle: 'ana-prueba',
        visibility: 'members',
        bio: '',
        bannerPublicId: null,
        showMembership: false,
        canShowMembership: false,
        nextHandleChangeAt: null,
        sections: [
            {
                key: 'core.about',
                label: 'Sobre mí',
                description: 'Lo que cuentas de ti.',
                enabled: true,
                region: 'aside',
                module: null,
                component: 'components/profileSections/AboutSection.vue',
            },
            {
                key: 'forum.latest',
                label: 'Últimos mensajes',
                description: 'Lo último que escribió.',
                enabled: false,
                region: 'main',
                module: 'forum',
                component: 'components/LatestPosts.vue',
            },
        ],
        stats: [],
        limits: {
            bio: 500,
            handleMin: 3,
            handleMax: 30,
            handleChangeDays: 30,
            bannerKilobytes: 5120,
            bannerTypes: ['jpg', 'jpeg', 'png', 'webp'],
        },
        urls: {
            update: '/miembros/ana-prueba',
            availability: '/miembros/ana-prueba/disponibilidad',
            banner: '/miembros/ana-prueba/banner',
        },
        ...overrides,
    };
}
