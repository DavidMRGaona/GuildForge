import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import MuteSubject from '../MuteSubject.vue';
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

const ACTION_URL =
    'https://example.test/correos/silenciar/u/example.campaign/c-1?nombre=x&signature=s';

function mountPage(muted: boolean): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(MuteSubject, {
        props: {
            subject: { label: 'La tumba <b>ya</b>', typeLabel: 'Campaña' },
            muted,
            actionUrl: ACTION_URL,
            preferencesUrl: '/perfil?tab=mail',
        },
        global: { plugins: [i18n] },
    });
}

describe('MuteSubject page', () => {
    beforeEach(() => {
        router.post.mockReset();
        router.delete.mockReset();
        setLayoutProps.mockReset();
        page.props = {};
    });

    it('asks to confirm and shows the name as plain text', () => {
        const wrapper = mountPage(false);

        expect(wrapper.text()).toContain('Campaña');
        expect(wrapper.text()).toContain(
            '¿Quieres dejar de recibir los avisos de «La tumba <b>ya</b>»?'
        );
        expect(wrapper.find('b').exists()).toBe(false);
        expect(setLayoutProps).toHaveBeenLastCalledWith({
            title: 'Dejar de recibir avisos',
            showBackLink: false,
        });
    });

    it('mutes with a POST to the signed address', async () => {
        const wrapper = mountPage(false);

        await wrapper.find('button').trigger('click');

        expect(router.post).toHaveBeenCalledWith(ACTION_URL, {}, expect.any(Object));
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

    it('updates the layout title when the same page comes back muted', async () => {
        const wrapper = mountPage(false);

        await wrapper.setProps({ muted: true });

        expect(setLayoutProps).toHaveBeenLastCalledWith({
            title: 'Avisos silenciados',
            showBackLink: false,
        });
    });
});
