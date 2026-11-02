import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProfileAccountTab from '../ProfileAccountTab.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { User } from '@/types/models';

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));

vi.mock('@inertiajs/vue3', () => ({
    Link: { template: '<a><slot /></a>' },
    router: { visit: vi.fn() },
    usePage: (): typeof page => page,
    useForm: <T extends object>(
        data: T
    ): T & { processing: boolean; errors: Record<string, string>; post: () => void } => ({
        ...data,
        processing: false,
        errors: {},
        post: vi.fn(),
    }),
}));

const baseUser: User = {
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

function mountTab(user: User): ReturnType<typeof mount> {
    page.props = { auth: { user } };
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ProfileAccountTab, { props: { user }, global: { plugins: [i18n] } });
}

function emailField(wrapper: ReturnType<typeof mount>): HTMLInputElement {
    return wrapper.get('input#email').element as HTMLInputElement;
}

describe('ProfileAccountTab', () => {
    it('fills the email field with the current email when no change awaits confirmation', () => {
        expect(emailField(mountTab(baseUser)).value).toBe('john@example.com');
    });

    // With the current email in the field, saving the name cancelled the pending change (#279)
    it('fills the email field with the pending email while its change awaits confirmation', () => {
        const wrapper = mountTab({ ...baseUser, pendingEmail: 'new@example.com' });

        expect(emailField(wrapper).value).toBe('new@example.com');
    });
});
