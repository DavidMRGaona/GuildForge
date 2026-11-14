import { beforeEach, describe, expect, it, vi } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import Register from '../Register.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { RegistrationField } from '@/types/models';

type FakeForm = Record<string, unknown> & {
    errors: Record<string, string>;
    processing: boolean;
    post: ReturnType<typeof vi.fn>;
    reset: ReturnType<typeof vi.fn>;
};

const page = vi.hoisted(() => ({ props: {} as Record<string, unknown> }));
const forms = vi.hoisted(() => ({ last: null as FakeForm | null }));

vi.mock('@inertiajs/vue3', async () => {
    const { reactive: makeReactive } = await import('vue');

    return {
        Link: { template: '<a><slot /></a>' },
        router: { visit: vi.fn() },
        setLayoutProps: vi.fn(),
        usePage: (): typeof page => page,
        useForm: <T extends object>(data: T): T & FakeForm => {
            const form = makeReactive({
                ...data,
                errors: {},
                processing: false,
                post: vi.fn(),
                reset: vi.fn(),
            }) as T & FakeForm;
            forms.last = form;

            return form;
        },
    };
});

const NEWSLETTER: RegistrationField = {
    key: 'newsletter.subscribe',
    label: 'Quiero recibir el boletín',
    description: 'Puedes darte de baja cuando quieras.',
    type: 'checkbox',
};

function mountPage(registrationFields?: RegistrationField[]): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(Register, {
        props: registrationFields === undefined ? {} : { registrationFields },
        global: { plugins: [i18n] },
    });
}

function form(): FakeForm {
    if (forms.last === null) {
        throw new Error('The page built no form');
    }

    return forms.last;
}

describe('Register page', () => {
    beforeEach(() => {
        forms.last = null;
        page.props = {
            authSettings: {
                registrationEnabled: true,
                loginEnabled: true,
                emailVerificationRequired: false,
            },
        };
    });

    it('draws no module fields when none are offered', () => {
        const wrapper = mountPage();

        expect(wrapper.findAll('input[type="checkbox"]')).toHaveLength(0);
        expect(form().registration_fields).toEqual({});
    });

    it('draws each module field unticked, with its label tied to the checkbox', () => {
        const wrapper = mountPage([NEWSLETTER]);

        const checkbox = wrapper.find('input[type="checkbox"]');
        expect(checkbox.exists()).toBe(true);
        expect((checkbox.element as HTMLInputElement).checked).toBe(false);
        expect(form().registration_fields).toEqual({ 'newsletter.subscribe': false });

        const id = checkbox.attributes('id');
        expect(id).toBe('registration-field-newsletter-subscribe');
        const label = wrapper.find(`label[for="${id}"]`);
        expect(label.text()).toContain('Quiero recibir el boletín');
    });

    it('describes the checkbox with the module text, as plain text', () => {
        const wrapper = mountPage([{ ...NEWSLETTER, description: 'Sin <b>spam</b>' }]);

        const checkbox = wrapper.find('input[type="checkbox"]');
        const description = wrapper.find('#registration-field-newsletter-subscribe-description');
        expect(description.text()).toBe('Sin <b>spam</b>');
        expect(wrapper.find('b').exists()).toBe(false);
        expect(checkbox.attributes('aria-describedby')).toBe(
            'registration-field-newsletter-subscribe-description'
        );
    });

    it('keeps the value the person ticks in the form', async () => {
        const wrapper = mountPage([NEWSLETTER]);

        await wrapper.find('input[type="checkbox"]').trigger('change');

        expect(form().registration_fields).toEqual({ 'newsletter.subscribe': true });
    });

    it('shows the error of a module field and marks the checkbox invalid', async () => {
        const wrapper = mountPage([{ ...NEWSLETTER, description: null }]);

        form().errors['registration_fields.newsletter.subscribe'] = 'Debes aceptar el boletín.';
        await wrapper.vm.$nextTick();

        const checkbox = wrapper.find('input[type="checkbox"]');
        expect(wrapper.find('#registration-field-newsletter-subscribe-error').text()).toBe(
            'Debes aceptar el boletín.'
        );
        expect(checkbox.attributes('aria-invalid')).toBe('true');
        expect(checkbox.attributes('aria-describedby')).toBe(
            'registration-field-newsletter-subscribe-error'
        );
    });

    it('draws fields in the order they come', () => {
        const wrapper = mountPage([
            NEWSLETTER,
            {
                key: 'other-module.updates',
                label: 'Novedades',
                description: null,
                type: 'checkbox',
            },
        ]);

        expect(
            wrapper.findAll('input[type="checkbox"]').map((input) => input.attributes('id'))
        ).toEqual([
            'registration-field-newsletter-subscribe',
            'registration-field-other-module-updates',
        ]);
        expect(form().registration_fields).toEqual({
            'newsletter.subscribe': false,
            'other-module.updates': false,
        });
    });
});
