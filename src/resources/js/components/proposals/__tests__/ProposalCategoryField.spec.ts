import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProposalCategoryField from '../ProposalCategoryField.vue';
import type { Tag } from '@/types/models';

const i18n = createI18n({
    legacy: false,
    locale: 'es',
    missingWarn: false,
    fallbackWarn: false,
    messages: { es: { proposals: { form: { category: 'Categoría' } } } },
});

const categories = [
    { id: 'cat-1', name: 'Rol', slug: 'rol' },
    { id: 'cat-2', name: 'Wargames', slug: 'wargames' },
] as Tag[];

describe('ProposalCategoryField', () => {
    it('offers one choice among the categories and marks it as required', () => {
        const wrapper = mount(ProposalCategoryField, {
            props: { categories, modelValue: 'cat-2' },
            global: { plugins: [i18n] },
        });

        const radios = wrapper.findAll('input[type="radio"]');
        expect(radios).toHaveLength(2);
        expect(new Set(radios.map((radio) => radio.attributes('name'))).size).toBe(1);
        expect((radios[1]!.element as HTMLInputElement).checked).toBe(true);
        expect(wrapper.find('legend').text()).toContain('Categoría');
        expect(wrapper.find('legend').text()).toContain('*');
    });

    it('picks the category whose pill is chosen', async () => {
        const wrapper = mount(ProposalCategoryField, {
            props: { categories, modelValue: null },
            global: { plugins: [i18n] },
        });

        await wrapper.findAll('input[type="radio"]')[0]!.setValue(true);

        expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toBe('cat-1');
    });

    it('shows the error tied to the group', () => {
        const wrapper = mount(ProposalCategoryField, {
            props: { categories, modelValue: null, error: 'Elige una categoría.' },
            global: { plugins: [i18n] },
        });

        const alert = wrapper.find('[role="alert"]');
        expect(alert.text()).toBe('Elige una categoría.');
        expect(wrapper.find('fieldset').attributes('aria-describedby')).toBe(
            alert.attributes('id')
        );
        expect(wrapper.find('fieldset').attributes('aria-invalid')).toBe('true');
    });

    it('draws nothing when no category is offered', () => {
        const wrapper = mount(ProposalCategoryField, {
            props: { categories: [], modelValue: null },
            global: { plugins: [i18n] },
        });

        expect(wrapper.find('fieldset').exists()).toBe(false);
    });
});
