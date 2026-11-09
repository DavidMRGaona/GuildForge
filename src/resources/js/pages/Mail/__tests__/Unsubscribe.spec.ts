import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import Unsubscribe from '../Unsubscribe.vue';
import en from '@/locales/en';
import es from '@/locales/es';

type VisitOptions = { onFinish?: () => void; preserveScroll?: boolean };

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
const router = vi.hoisted(() => ({ post: vi.fn(), delete: vi.fn() }));
const setLayoutProps = vi.hoisted(() => vi.fn());

vi.mock('@inertiajs/vue3', () => ({
    router,
    setLayoutProps,
    usePage: (): typeof page => page,
}));

const ACTION_URL = 'https://example.test/correos/baja/u/events?signature=s';

function mountPage(unsubscribed: boolean, mandatory = false): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(Unsubscribe, {
        props: {
            category: {
                label: 'Eventos',
                mandatory,
                mandatoryReason: mandatory ? 'Son avisos de tu cuenta.' : null,
            },
            unsubscribed,
            actionUrl: ACTION_URL,
            preferencesUrl: '/perfil?tab=mail',
        },
        global: { plugins: [i18n] },
    });
}

describe('Unsubscribe page', () => {
    beforeEach(() => {
        router.post.mockReset();
        router.delete.mockReset();
        setLayoutProps.mockReset();
        page.props = {};
    });

    it('asks to confirm the unsubscription', () => {
        const wrapper = mountPage(false);

        expect(wrapper.text()).toContain('¿Quieres dejar de recibir los correos de «Eventos»?');
        expect(setLayoutProps).toHaveBeenLastCalledWith({
            title: 'Darte de baja',
            showBackLink: false,
        });
    });

    it('unsubscribes with a POST to the signed address and brings the button back', async () => {
        const wrapper = mountPage(false);

        await wrapper.find('button').trigger('click');
        expect(wrapper.find('button').text()).toBe('Cargando...');

        const [url, data, options] = router.post.mock.calls[0] as [string, object, VisitOptions];
        expect(url).toBe(ACTION_URL);
        expect(data).toEqual({});
        expect(options.preserveScroll).toBe(true);
        options.onFinish?.();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('button').text()).toBe('Darme de baja');
    });

    it('undoes with a DELETE whose options are its second argument, so the button comes back', async () => {
        const wrapper = mountPage(true);

        await wrapper.find('button').trigger('click');
        expect(wrapper.find('button').text()).toBe('Cargando...');

        const [url, options] = router.delete.mock.calls[0] as [string, VisitOptions];
        expect(url).toBe(ACTION_URL);
        expect(options.preserveScroll).toBe(true);
        options.onFinish?.();
        await wrapper.vm.$nextTick();

        expect(wrapper.find('button').text()).toBe('Deshacer');
    });

    it('updates the layout title when the same page comes back in the other state', async () => {
        const wrapper = mountPage(false);

        await wrapper.setProps({ unsubscribed: true });
        expect(setLayoutProps).toHaveBeenLastCalledWith({
            title: 'Te has dado de baja',
            showBackLink: false,
        });

        await wrapper.setProps({ unsubscribed: false });
        expect(setLayoutProps).toHaveBeenLastCalledWith({
            title: 'Darte de baja',
            showBackLink: false,
        });
    });

    it('explains a mandatory category without offering to unsubscribe', () => {
        const wrapper = mountPage(false, true);

        expect(wrapper.text()).toContain('Los correos de «Eventos» siempre se envían.');
        expect(wrapper.text()).toContain('Son avisos de tu cuenta.');
        expect(wrapper.find('button').exists()).toBe(false);
        expect(setLayoutProps).toHaveBeenLastCalledWith({
            title: 'Este correo siempre se envía',
            showBackLink: false,
        });
    });
});
