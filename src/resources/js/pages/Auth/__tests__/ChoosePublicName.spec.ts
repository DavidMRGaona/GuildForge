import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ChoosePublicName from '../ChoosePublicName.vue';
import en from '@/locales/en';
import es from '@/locales/es';

type FakeForm = Record<string, unknown> & {
    errors: Record<string, string>;
    processing: boolean;
    post: ReturnType<typeof vi.fn>;
};

const forms = vi.hoisted(() => ({ last: null as FakeForm | null }));
const auth = vi.hoisted(() => ({ logout: vi.fn() }));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive: makeReactive } = await import('vue');

    return {
        setLayoutProps: vi.fn(),
        useForm: <T extends object>(data: T): T & FakeForm => {
            const form = makeReactive({
                ...data,
                errors: {},
                processing: false,
                post: vi.fn(),
            }) as T & FakeForm;
            forms.last = form;

            return form;
        },
    };
});

vi.mock('@/composables/useAuth', () => ({ useAuth: (): typeof auth => auth }));
vi.mock('@/composables/useNotifications', () => ({
    useNotifications: (): { error: ReturnType<typeof vi.fn> } => ({ error: vi.fn() }),
}));

function mountPage(suggestion: string): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(ChoosePublicName, { props: { suggestion }, global: { plugins: [i18n] } });
}

function form(): FakeForm {
    if (forms.last === null) {
        throw new Error('The page built no form');
    }

    return forms.last;
}

describe('ChoosePublicName page', () => {
    beforeEach(() => {
        forms.last = null;
        auth.logout.mockClear();
    });

    it('comes filled with the suggestion, which the person can change', async () => {
        const wrapper = mountPage('Ana');
        const input = wrapper.get('#display_name');

        expect((input.element as HTMLInputElement).value).toBe('Ana');
        expect(input.attributes('required')).toBeDefined();

        await input.setValue('Lady Ana');

        expect(form().display_name).toBe('Lady Ana');
    });

    it('saves only when the person confirms', async () => {
        const wrapper = mountPage('Ana');

        expect(form().post).not.toHaveBeenCalled();

        await wrapper.get('form').trigger('submit');

        expect(form().post).toHaveBeenCalledWith('/perfil/nombre-publico', expect.any(Object));
    });

    it('explains why the name is needed and that the account name stays private', () => {
        const text = mountPage('Ana').text();

        expect(text).toContain('Es el nombre con el que te verán las demás personas en la web');
        expect(text).toContain(
            'Nunca mostramos públicamente el nombre con el que te registraste sin tu consentimiento.'
        );
        expect(text).toContain('Guardar y continuar');
    });

    it('shows the error of the field and marks it invalid', async () => {
        const wrapper = mountPage('');
        form().errors = { display_name: 'El campo nombre público es obligatorio.' };
        await wrapper.vm.$nextTick();

        const input = wrapper.get('#display_name');
        expect(input.attributes('aria-invalid')).toBe('true');
        expect(input.attributes('aria-describedby')).toBe('display_name-error');
        expect(wrapper.get('#display_name-error').text()).toBe(
            'El campo nombre público es obligatorio.'
        );
    });

    it('lets the person sign out instead', async () => {
        const wrapper = mountPage('Ana');

        await wrapper.findAll('button').at(-1)?.trigger('click');

        expect(auth.logout).toHaveBeenCalledOnce();
    });
});
