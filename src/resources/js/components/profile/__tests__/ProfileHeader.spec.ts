import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileHeader from '../ProfileHeader.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { User } from '@/types/models';

const forms = vi.hoisted(() => ({ created: [] as Record<string, unknown>[] }));

vi.mock('@inertiajs/vue3', () => ({
    useForm: <T extends object>(data: T): T & { processing: boolean; post: () => void } => {
        const form = { ...data, processing: false, post: vi.fn() };
        forms.created.push(form);

        return form;
    },
}));

const user: User = {
    id: '1',
    name: 'John Doe',
    displayName: null,
    email: 'john@example.com',
    pendingEmail: 'new@example.com',
    avatarPublicId: null,
    emailVerified: true,
    createdAt: '2026-01-01T00:00:00Z',
    roles: ['member'],
    permissions: [],
};

function mountHeader(): ReturnType<typeof mount> {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ProfileHeader, {
        props: { user },
        global: { plugins: [i18n], stubs: { HelpLink: true } },
    });
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

    // Sending the account fields with the photo cancelled a pending email change (#279)
    it('uploads the photo without the name or the email', async () => {
        forms.created.length = 0;
        const wrapper = mountHeader();
        const input = wrapper.get('input[type="file"]');
        const file = new File(['image'], 'avatar.png', { type: 'image/png' });
        Object.defineProperty(input.element, 'files', { value: [file] });
        URL.createObjectURL = vi.fn(() => 'blob:avatar');

        await input.trigger('change');

        const form = forms.created.at(-1);
        expect(form?.post).toHaveBeenCalledWith('/perfil', expect.anything());
        expect(form).toMatchObject({ avatar: file });
        expect(form).not.toHaveProperty('email');
        expect(form).not.toHaveProperty('name');
        expect(form).not.toHaveProperty('display_name');
    });

    it('links the profile guide next to the name', () => {
        const link = mountHeader().get('h1 + help-link-stub');

        expect(link.attributes('guide')).toBe('core.account.complete-profile');
    });
});
