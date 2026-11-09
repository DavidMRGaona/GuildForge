import { describe, expect, it } from 'vitest';
import { h } from 'vue';
import { mount } from '@vue/test-utils';
import MailPreferenceRow from '../MailPreferenceRow.vue';
import type { MailPreference } from '@/types/mailPreferences';

const CATEGORY: MailPreference = {
    key: 'game-tables.reminders',
    label: 'Recordatorios',
    description: 'Antes de que empiece una partida.',
    mandatory: false,
    mandatoryReason: null,
    enabled: true,
};

describe('MailPreferenceRow', () => {
    it('draws a switch by default, labelled and described by its text', () => {
        const wrapper = mount(MailPreferenceRow, {
            props: { category: CATEGORY, modelValue: true },
        });

        const control = wrapper.get('[role="switch"]');
        expect(control.attributes('aria-checked')).toBe('true');
        expect(wrapper.get(`#${control.attributes('aria-labelledby') ?? ''}`).text()).toBe(
            'Recordatorios'
        );
    });

    it('locks the switch while busy but keeps it focusable', async () => {
        const wrapper = mount(MailPreferenceRow, {
            props: { category: CATEGORY, modelValue: true, busy: true },
        });

        const control = wrapper.get('[role="switch"]');
        expect(control.attributes('aria-disabled')).toBe('true');
        expect(control.attributes('disabled')).toBeUndefined();
        await control.trigger('click');
        expect(wrapper.emitted('update:modelValue')).toBeUndefined();
    });

    it('lets the parent replace the switch with another control, handing it the ids to use', () => {
        const wrapper = mount(MailPreferenceRow, {
            props: { category: CATEGORY, modelValue: true, error: 'No se puede.' },
            slots: {
                control: (slot: { labelId: string; describedBy: string; locked: boolean }) =>
                    h('select', {
                        'aria-labelledby': slot.labelId,
                        'aria-describedby': slot.describedBy,
                    }),
            },
        });

        expect(wrapper.find('[role="switch"]').exists()).toBe(false);
        const select = wrapper.get('select');
        expect(wrapper.get(`#${select.attributes('aria-labelledby') ?? ''}`).text()).toBe(
            'Recordatorios'
        );
        const described = (select.attributes('aria-describedby') ?? '').split(' ');
        expect(described.map((id) => wrapper.get(`#${id}`).text())).toContain('No se puede.');
    });
});
