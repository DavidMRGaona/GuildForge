import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import FormTagsInput from '../FormTagsInput.vue';
import en from '@/locales/en';
import es from '@/locales/es';

function mountTags(locale: 'es' | 'en', modelValue: string[]): ReturnType<typeof mount> {
    const i18n = createI18n({ legacy: false, locale, messages: { es, en } });

    return mount(FormTagsInput, {
        props: { modelValue },
        global: { plugins: [i18n] },
    });
}

describe('FormTagsInput', () => {
    it('names the remove button after the tag, in the current language', () => {
        const wrapper = mountTags('en', ['Gore']);

        expect(wrapper.get('button[type="button"]').attributes('aria-label')).toBe('Remove Gore');
    });

    it('gives the add button an accessible name', async () => {
        const wrapper = mountTags('es', []);

        await wrapper.get('input').setValue('Arañas');

        expect(wrapper.get('button[type="button"]').attributes('aria-label')).toBe(
            'Añadir etiqueta'
        );
    });
});
