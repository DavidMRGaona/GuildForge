import { describe, expect, it } from 'vitest';
import { bioPreviewLines, codePointLength, previewProfile } from '../profilePreview';
import type { ProfileEditor, ProfileEditorForm } from '@/types/profileEditor';
import type { ResolvedProfile, ResolvedProfileSection } from '@/types/profileSections';

function section(
    key: string,
    region: 'main' | 'aside',
    props: Record<string, unknown> = {}
): ResolvedProfileSection {
    return { key, module: null, component: `components/${key}.vue`, region, label: key, props };
}

const about: ResolvedProfileSection = {
    key: 'core.about',
    module: null,
    component: 'components/profileSections/AboutSection.vue',
    region: 'aside',
    label: 'Sobre mí',
    props: {
        lines: [
            [
                { text: 'Mira ', href: null },
                { text: 'https://example.org', href: 'https://example.org' },
            ],
        ],
    },
};

function profile(overrides: Partial<ResolvedProfile> = {}): ResolvedProfile {
    return {
        header: {
            handle: 'ana-prueba',
            publicName: 'Ana Prueba',
            avatarPublicId: null,
            bannerPublicId: null,
            memberSince: null,
            showsMembership: false,
            isOwner: true,
        },
        stats: [
            { key: 'forum.posts', label: 'Mensajes', value: 4, url: null },
            { key: 'gt.tables', label: 'Mesas', value: 2, url: null },
        ],
        sections: {
            main: [section('forum.latest', 'main'), section('gt.tables', 'main')],
            aside: [about],
        },
        ...overrides,
    };
}

function editor(overrides: Partial<ProfileEditor> = {}): ProfileEditor {
    return {
        handle: 'ana-prueba',
        visibility: 'members',
        bio: 'Mira https://example.org',
        bannerPublicId: null,
        showMembership: false,
        canShowMembership: false,
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
            {
                key: 'forum.latest',
                label: 'Mensajes',
                description: '',
                enabled: true,
                region: 'main',
                module: 'forum',
                component: 'components/X.vue',
            },
            {
                key: 'gt.tables',
                label: 'Mesas',
                description: '',
                enabled: true,
                region: 'main',
                module: 'gt',
                component: 'components/Y.vue',
            },
        ],
        stats: [
            { key: 'forum.posts', label: 'Mensajes', description: '', enabled: true },
            { key: 'gt.tables', label: 'Mesas', description: '', enabled: true },
        ],
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
        ...overrides,
    };
}

function form(overrides: Partial<ProfileEditorForm> = {}): ProfileEditorForm {
    return {
        handle: 'ana-prueba',
        visibility: 'members',
        bio: 'Mira https://example.org',
        show_membership: false,
        sections: [
            { key: 'core.about', enabled: true },
            { key: 'forum.latest', enabled: true },
            { key: 'gt.tables', enabled: true },
        ],
        stats: [
            { key: 'forum.posts', enabled: true },
            { key: 'gt.tables', enabled: true },
        ],
        ...overrides,
    };
}

describe('previewProfile', () => {
    it('shows the profile as it is while nothing changed', () => {
        expect(previewProfile(profile(), editor(), form())).toEqual(profile());
    });

    it('reorders and hides sections and stats as the owner does', () => {
        const preview = previewProfile(
            profile(),
            editor(),
            form({
                sections: [
                    { key: 'gt.tables', enabled: true },
                    { key: 'forum.latest', enabled: false },
                    { key: 'core.about', enabled: true },
                ],
                stats: [
                    { key: 'gt.tables', enabled: true },
                    { key: 'forum.posts', enabled: false },
                ],
            })
        );

        expect(preview.sections.main.map((s) => s.key)).toEqual(['gt.tables']);
        expect(preview.sections.aside.map((s) => s.key)).toEqual(['core.about']);
        expect(preview.stats.map((s) => s.key)).toEqual(['gt.tables']);
    });

    it('cannot show a section or stat turned on that the server did not draw', () => {
        const preview = previewProfile(
            profile({ stats: [], sections: { main: [], aside: [about] } }),
            editor(),
            form()
        );

        expect(preview.sections.main).toEqual([]);
        expect(preview.stats).toEqual([]);
    });

    it('draws the text being written as plain lines, and keeps the links of the saved one', () => {
        const saved = previewProfile(profile(), editor(), form());
        expect(saved.sections.aside[0]?.props).toEqual(about.props);

        const edited = previewProfile(
            profile(),
            editor(),
            form({ bio: 'Hola\n\n\nhttps://x.org' })
        );
        expect(edited.sections.aside[0]?.props).toEqual({
            lines: [[{ text: 'Hola', href: null }], [], [{ text: 'https://x.org', href: null }]],
        });

        const emptied = previewProfile(profile(), editor(), form({ bio: '   ' }));
        expect(emptied.sections.aside).toEqual([]);
    });

    it('adds «Sobre mí» once there is a text to show', () => {
        const preview = previewProfile(
            profile({ sections: { main: [], aside: [] } }),
            editor({ bio: '' }),
            form({ bio: 'Nuevo' })
        );

        expect(preview.sections.aside).toEqual([
            {
                key: 'core.about',
                module: null,
                component: 'components/profileSections/AboutSection.vue',
                region: 'aside',
                label: 'Sobre mí',
                props: { lines: [[{ text: 'Nuevo', href: null }]] },
            },
        ]);
    });

    it('shows the badge only to an association member who chose it', () => {
        expect(
            previewProfile(profile(), editor(), form({ show_membership: true })).header
                .showsMembership
        ).toBe(false);
        expect(
            previewProfile(
                profile(),
                editor({ canShowMembership: true }),
                form({ show_membership: true })
            ).header.showsMembership
        ).toBe(true);
        expect(
            previewProfile(
                profile({ header: { ...profile().header, showsMembership: true } }),
                editor({ canShowMembership: true }),
                form({ show_membership: false })
            ).header.showsMembership
        ).toBe(false);
    });
});

describe('bioPreviewLines', () => {
    it('cuts to the limit counted in characters and drops control characters', () => {
        expect(bioPreviewLines('a\u0007b‮c', 500)).toEqual([[{ text: 'abc', href: null }]]);
        expect(bioPreviewLines('😀'.repeat(3), 2)).toEqual([[{ text: '😀😀', href: null }]]);
        expect(bioPreviewLines('\n\nHola\n\n', 500)).toEqual([[{ text: 'Hola', href: null }]]);
    });
});

describe('codePointLength', () => {
    it('counts characters as the server does, not UTF-16 units', () => {
        expect(codePointLength('ñandú')).toBe(5);
        expect(codePointLength('😀')).toBe(1);
    });
});
