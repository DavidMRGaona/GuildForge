import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import ProposalTagsField from '../ProposalTagsField.vue';
import type { Tag } from '@/types/models';

const i18n = createI18n({
    legacy: false,
    locale: 'es',
    missingWarn: false,
    fallbackWarn: false,
    messages: { es: { proposals: { form: { tags: 'Etiquetas' } } } },
});

const tags = [
    { id: 'tag-1', name: 'Juegos de rol', slug: 'juegos-de-rol' },
    { id: 'tag-2', name: 'Taller', slug: 'taller' },
] as Tag[];

describe('ProposalTagsField', () => {
    it('draws the site checkbox instead of the browser one', () => {
        const wrapper = mount(ProposalTagsField, {
            props: { tags, modelValue: ['tag-2'] },
            global: { plugins: [i18n] },
        });

        const inputs = wrapper.findAll('input[type="checkbox"]');
        expect(inputs).toHaveLength(2);
        inputs.forEach((input) => expect(input.classes()).toContain('sr-only'));
        expect((inputs[1]!.element as HTMLInputElement).checked).toBe(true);
    });

    it('adds and removes a tag when its box is ticked', async () => {
        const wrapper = mount(ProposalTagsField, {
            props: { tags, modelValue: ['tag-2'] },
            global: { plugins: [i18n] },
        });

        await wrapper.findAll('input[type="checkbox"]')[0]!.trigger('change');
        expect(wrapper.emitted('update:modelValue')?.[0]?.[0]).toEqual(['tag-2', 'tag-1']);

        await wrapper.findAll('input[type="checkbox"]')[1]!.trigger('change');
        expect(wrapper.emitted('update:modelValue')?.[1]?.[0]).toEqual(['tag-1']);
    });
});
