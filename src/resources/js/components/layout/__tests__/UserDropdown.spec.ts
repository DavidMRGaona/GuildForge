import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive } from 'vue';
import UserDropdown from '../UserDropdown.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { User } from '@/types/models';

const page = reactive<{ url: string; props: { auth: { user: User | null } } }>({
    url: '/',
    props: { auth: { user: null } },
});

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): typeof page => page,
    router: { post: vi.fn() },
    Link: { template: '<a><slot /></a>' },
}));

function userWith(roles: string[], permissions: string[]): User {
    return {
        id: '1',
        name: 'John Doe',
        displayName: null,
        email: 'john@example.com',
        pendingEmail: null,
        avatarPublicId: null,
        emailVerified: true,
        createdAt: '2026-01-01T00:00:00Z',
        roles,
        permissions,
    };
}

async function openMenuFor(user: User): Promise<ReturnType<typeof mount>> {
    page.props.auth.user = user;
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });
    const wrapper = mount(UserDropdown, { global: { plugins: [i18n] } });

    await wrapper.get('button[aria-expanded]').trigger('click');

    return wrapper;
}

describe('UserDropdown', () => {
    it('links to the panel for a role with admin.access, whatever its name', async () => {
        const wrapper = await openMenuFor(userWith(['member', 'treasury'], ['admin.access']));

        expect(wrapper.find('a[href="/admin"]').exists()).toBe(true);
    });

    it('links to the panel for an admin', async () => {
        const wrapper = await openMenuFor(userWith(['admin'], []));

        expect(wrapper.find('a[href="/admin"]').exists()).toBe(true);
    });

    it('caps the width of a long name and keeps it whole for hover and screen readers', async () => {
        const longName = 'Bartholomew Montgomery-Fitzwilliam';
        const wrapper = await openMenuFor({ ...userWith(['member'], []), displayName: longName });

        const name = wrapper.get(`[title="${longName}"]`);
        expect(name.text()).toBe(longName);
        expect(name.classes()).toEqual(expect.arrayContaining(['max-w-40', 'truncate']));
    });

    it('hides the panel link from an editor role without admin.access', async () => {
        const wrapper = await openMenuFor(userWith(['member', 'editor'], ['events.create']));

        expect(wrapper.find('a[href="/admin"]').exists()).toBe(false);
    });
});
