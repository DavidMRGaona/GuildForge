import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileHeader from '../ProfileHeader.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { User } from '@/types/models';

vi.mock('@inertiajs/vue3', () => ({
    useForm: <T extends object>(data: T): T & { processing: boolean; post: () => void } => ({
        ...data,
        processing: false,
        post: vi.fn(),
    }),
}));

const user: User = {
    id: '1',
    name: 'John Doe',
    displayName: null,
    email: 'john@example.com',
    pendingEmail: null,
    avatarPublicId: null,
    emailVerified: true,
    createdAt: '2026-01-01T00:00:00Z',
    roles: ['member'],
    permissions: [],
};

function mountHeader(): ReturnType<typeof mount> {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ProfileHeader, { props: { user }, global: { plugins: [i18n] } });
}

function classesOf(element: Element): string[] {
    return Array.from(element.classList);
}

describe('ProfileHeader', () => {
    // Theme tokens flip with the site palette and the theme; a neutral-* / dark: pair gave 4.18:1 in light mode (#269)
    it('draws the member-since chip with theme tokens that stay readable on the muted header', () => {
        const wrapper = mountHeader();
        const chip = wrapper.get('h1 ~ p.rounded-full').element;

        expect(classesOf(chip)).toEqual(
            expect.arrayContaining([
                'bg-surface',
                'border',
                'border-default',
                'text-base-secondary',
            ])
        );
        expect(classesOf(chip).filter((name) => /^(dark:|bg-neutral-)/.test(name))).toEqual([]);
    });

    it('paints the header with the muted token in both themes', () => {
        const header = mountHeader().element as Element;

        expect(classesOf(header)).toContain('bg-muted');
        expect(classesOf(header).filter((name) => /^(dark:bg-|bg-neutral-)/.test(name))).toEqual(
            []
        );
    });
});
