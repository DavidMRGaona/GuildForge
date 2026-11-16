import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import TwoFactorChallenge from '../TwoFactorChallenge.vue';
import en from '@/locales/en';
import es from '@/locales/es';

type FakeForm = Record<string, unknown> & {
    code: string;
    recovery: boolean;
    errors: Record<string, string>;
    processing: boolean;
    post: ReturnType<typeof vi.fn>;
    reset: ReturnType<typeof vi.fn>;
    clearErrors: ReturnType<typeof vi.fn>;
};

const forms = vi.hoisted(() => ({ last: null as FakeForm | null }));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive: makeReactive } = await import('vue');

    return {
        Link: { template: '<a><slot /></a>' },
        setLayoutProps: vi.fn(),
        usePage: (): { props: Record<string, unknown> } => ({ props: {} }),
        useForm: <T extends object>(data: T): T & FakeForm => {
            const form = makeReactive({
                ...data,
                errors: {},
                processing: false,
                post: vi.fn(),
                reset: vi.fn(),
                clearErrors: vi.fn(),
            }) as T & FakeForm;
            forms.last = form;

            return form;
        },
    };
});

function mountPage(): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(TwoFactorChallenge, { global: { plugins: [i18n] } });
}

function form(): FakeForm {
    if (forms.last === null) {
        throw new Error('The page built no form');
    }

    return forms.last;
}

describe('TwoFactorChallenge page', () => {
    beforeEach(() => {
        forms.last = null;
    });

    it('asks for the app code first, as a one-time code', () => {
        const wrapper = mountPage();
        const input = wrapper.get('input#code');

        expect(wrapper.get('label[for="code"]').text()).toBe(es.auth.twoFactorChallenge.appCode);
        expect(input.attributes('autocomplete')).toBe('one-time-code');
        expect(input.attributes('inputmode')).toBe('numeric');
        expect(form().recovery).toBe(false);
    });

    it('switches to a recovery code and back, clearing what was typed', async () => {
        const wrapper = mountPage();
        form().code = '123';

        await wrapper.get('button[type="button"]').trigger('click');

        expect(form().recovery).toBe(true);
        expect(form().code).toBe('');
        expect(wrapper.get('label[for="code"]').text()).toBe(
            es.auth.twoFactorChallenge.recoveryCode
        );
        expect(wrapper.get('input#code').attributes('autocomplete')).toBe('off');

        await wrapper.get('button[type="button"]').trigger('click');

        expect(form().recovery).toBe(false);
    });

    it('posts the code to the challenge', async () => {
        const wrapper = mountPage();
        form().code = '123456';

        await wrapper.get('form').trigger('submit');

        expect(form().post).toHaveBeenCalledWith(
            '/iniciar-sesion/verificacion',
            expect.any(Object)
        );
    });

    it('shows the error the server gave for the code', async () => {
        const wrapper = mountPage();
        form().errors = { code: 'El código no es válido o ya se ha usado.' };
        await wrapper.vm.$nextTick();

        expect(wrapper.get('[role="alert"]').text()).toBe(
            'El código no es válido o ya se ha usado.'
        );
        expect(wrapper.get('input#code').attributes('aria-invalid')).toBe('true');
    });
});
