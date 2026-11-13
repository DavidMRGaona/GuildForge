import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { reactive } from 'vue';
import ImpersonationBanner from '../ImpersonationBanner.vue';
import es from '@/locales/es';
import en from '@/locales/en';

const page = reactive<{ props: Record<string, unknown> }>({ props: {} });
const post = vi.fn();
const form = reactive({ processing: false, post });

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): typeof page => page,
    useForm: (): typeof form => form,
}));

function mountBanner(): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ImpersonationBanner, { global: { plugins: [i18n] } });
}

describe('ImpersonationBanner', () => {
    beforeEach(() => {
        post.mockReset();
        form.processing = false;
        page.props = { impersonation: null };
    });

    it('renders nothing without an impersonation', () => {
        expect(mountBanner().find('[data-testid="impersonation-banner"]').exists()).toBe(false);
    });

    it('names the account being viewed and explains the read-only mode', () => {
        page.props = { impersonation: { targetName: 'Ana Example', mode: 'read_only' } };

        const wrapper = mountBanner();

        expect(wrapper.text()).toContain('Estás viendo el sitio como Ana Example');
        expect(wrapper.text()).toContain('Solo mirar: no se guardan cambios');
        expect(wrapper.get('[role="region"]').attributes('aria-label')).toBe('Impersonación');
        expect(wrapper.get('[data-testid="impersonation-banner"]').classes()).toContain(
            'bg-info-light'
        );
    });

    it('explains the simulate mode with another colour', () => {
        page.props = { impersonation: { targetName: 'Ana Example', mode: 'simulate' } };

        const wrapper = mountBanner();

        expect(wrapper.text()).toContain(
            'Ejecutar y deshacer: lo que envíes se ejecuta y se deshace'
        );
        expect(wrapper.text()).not.toContain('Solo mirar');
        expect(wrapper.get('[data-testid="impersonation-banner"]').classes()).toContain(
            'bg-warning-light'
        );
        expect(wrapper.get('[data-testid="impersonation-banner"]').classes()).not.toContain(
            'bg-info-light'
        );
    });

    it('shows a notice for the administrator as an alert', () => {
        page.props = {
            impersonation: {
                targetName: 'Ana Example',
                mode: 'read_only',
                notice: 'Estás viendo el sitio como Ana Example: no se guardan cambios.',
            },
        };

        const notice = mountBanner().get('[data-testid="impersonation-notice"]');

        expect(notice.attributes('role')).toBe('alert');
        expect(notice.text()).toBe(
            'Estás viendo el sitio como Ana Example: no se guardan cambios.'
        );
    });

    it('shows no notice when there is none', () => {
        page.props = {
            impersonation: { targetName: 'Ana Example', mode: 'read_only', notice: null },
        };

        expect(mountBanner().find('[data-testid="impersonation-notice"]').exists()).toBe(false);
    });

    it('posts to the stop route from the button', async () => {
        page.props = { impersonation: { targetName: 'Ana Example', mode: 'read_only' } };

        const wrapper = mountBanner();
        await wrapper.get('button').trigger('click');

        expect(wrapper.get('button').text()).toBe('Volver a mi cuenta');
        expect(post).toHaveBeenCalledWith('/impersonar/salir');
    });

    it('disables the button while going back', () => {
        page.props = { impersonation: { targetName: 'Ana Example', mode: 'simulate' } };
        form.processing = true;

        const wrapper = mountBanner();

        expect(wrapper.get('button').attributes('disabled')).toBeDefined();
    });
});
