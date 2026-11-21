import { describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive } from 'vue';
import UserDropdown from '../UserDropdown.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { User } from '@/types/models';
import { setCloudinaryConfig } from '@/utils/cloudinary';

const page = reactive<{
    url: string;
    props: { auth: { user: User | null }; webPush?: { publicKey: string } | null };
}>({
    url: '/',
    props: { auth: { user: null } },
});

const { post, forgetThisDevice } = vi.hoisted(() => ({
    post: vi.fn(),
    forgetThisDevice: vi.fn(),
}));

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): typeof page => page,
    router: { post },
    Link: { template: '<a><slot /></a>' },
}));

vi.mock('@/utils/webPush', () => ({ forgetThisDevice }));

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
        publicProfilePath: '/miembros/ana-ejemplo',
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

    it('links to the help centre with a translated label', async () => {
        const wrapper = await openMenuFor(userWith(['member'], []));

        expect(wrapper.text()).toContain('Ayuda');
        expect(wrapper.text()).not.toContain('auth.userMenu.help');
        expect(wrapper.find('a[href="/ayuda"]').exists()).toBe(true);
        expect(en.auth.userMenu.help).toBe('Help');
    });

    describe('profile entries (core#745)', () => {
        it('sends «Mi perfil» to the public profile address the server shared', async () => {
            const wrapper = await openMenuFor(userWith(['member'], []));

            const item = wrapper.get('a[href="/miembros/ana-ejemplo"]');
            expect(item.text()).toBe('Mi perfil');
            expect(item.attributes('role')).toBe('menuitem');
        });

        it('sends «Ajustes de la cuenta» to /perfil, whose address does not change', async () => {
            const wrapper = await openMenuFor(userWith(['member'], []));

            const item = wrapper.get('a[href="/perfil"]');
            expect(item.text()).toBe('Ajustes de la cuenta');
            expect(item.attributes('role')).toBe('menuitem');
        });

        it('shows the public profile entry before the account settings', async () => {
            const wrapper = await openMenuFor(userWith(['member'], []));

            const labels = wrapper.findAll('[role="menuitem"]').map((item) => item.text());
            expect(labels.indexOf('Mi perfil')).toBeGreaterThanOrEqual(0);
            expect(labels.indexOf('Mi perfil')).toBeLessThan(
                labels.indexOf('Ajustes de la cuenta')
            );
        });

        it.each(['null', 'absent'])(
            'leaves «Mi perfil» out when the account has no profile (%s)',
            async (kind) => {
                const account = userWith(['member'], []);
                if (kind === 'null') {
                    account.publicProfilePath = null;
                } else {
                    delete account.publicProfilePath;
                }
                const wrapper = await openMenuFor(account);

                expect(wrapper.text()).not.toContain('Mi perfil');
                expect(wrapper.find('a[href^="/miembros"]').exists()).toBe(false);
                expect(wrapper.find('a[href="/perfil"]').exists()).toBe(true);
            }
        );

        it('never builds the address in the browser: it is exactly what the server sent', async () => {
            const wrapper = await openMenuFor({
                ...userWith(['member'], []),
                displayName: 'Otro Nombre',
                publicProfilePath: '/miembros/direccion-elegida',
            });

            expect(wrapper.find('a[href="/miembros/direccion-elegida"]').exists()).toBe(true);
            expect(wrapper.find('a[href="/miembros/otro-nombre"]').exists()).toBe(false);
        });

        it('translates both labels', () => {
            expect(en.auth.userMenu.profile).toBe('My profile');
            expect(en.auth.userMenu.accountSettings).toBe('Account settings');
            expect(es.auth.userMenu.accountSettings).toBe('Ajustes de la cuenta');
        });
    });

    it('shows the avatar beside the name, with an empty alt since the name says who it is', async () => {
        setCloudinaryConfig({ cloudName: 'demo', prefix: '' });
        const wrapper = await openMenuFor({
            ...userWith(['member'], []),
            avatarPublicId: 'users/1/avatar.jpg',
        });

        const avatar = wrapper.get('[data-testid="user-menu-avatar"] img');
        expect(avatar.attributes('src')).toContain('/users/1/avatar');
        expect(avatar.attributes('src')).toContain('w_56,h_56');
        expect(avatar.attributes('alt')).toBe('');
    });

    it('shows the initials of the shown name when there is no avatar', async () => {
        const wrapper = await openMenuFor({
            ...userWith(['member'], []),
            displayName: 'Ana María Prueba',
        });

        const avatar = wrapper.get('[data-testid="user-menu-avatar"]');
        expect(avatar.find('img').exists()).toBe(false);
        expect(avatar.text()).toBe('AP');
        expect(avatar.attributes('aria-hidden')).toBe('true');
    });

    it('falls back to the person icon when the name gives no initials', async () => {
        const wrapper = await openMenuFor({ ...userWith(['member'], []), name: '***' });

        const avatar = wrapper.get('[data-testid="user-menu-avatar"]');
        expect(avatar.text()).toBe('');
        expect(avatar.find('svg').exists()).toBe(true);
    });

    it('shows the initials instead of a broken image when the photo fails to load', async () => {
        setCloudinaryConfig({ cloudName: 'demo', prefix: '' });
        const wrapper = await openMenuFor({
            ...userWith(['member'], []),
            displayName: 'Ana Prueba',
            avatarPublicId: 'users/1/gone.jpg',
        });

        await wrapper.get('[data-testid="user-menu-avatar"] img').trigger('error');

        const avatar = wrapper.get('[data-testid="user-menu-avatar"]');
        expect(avatar.find('img').exists()).toBe(false);
        expect(avatar.text()).toBe('AP');
    });

    it('signs out through useAuth, so this browser stops getting push notifications first', async () => {
        page.props.webPush = { publicKey: 'key' };
        const order: string[] = [];
        forgetThisDevice.mockImplementation(async () => void order.push('forget'));
        post.mockImplementation(() => void order.push('post'));
        const wrapper = await openMenuFor(userWith(['member'], []));

        const signOut = wrapper
            .findAll('button')
            .find((button) => button.text().includes('Cerrar sesión'));
        await signOut?.trigger('click');
        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(order).toEqual(['forget', 'post']);
        expect(post).toHaveBeenCalledWith('/cerrar-sesion');
        expect(wrapper.emitted('navigate')).toHaveLength(1);
        page.props.webPush = null;
    });
});
