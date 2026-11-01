import { afterEach, describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import FormTimePicker from '../FormTimePicker.vue';
import en from '@/locales/en';
import es from '@/locales/es';

const mounted: VueWrapper[] = [];

function mountPicker(props: Record<string, unknown> = {}): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });
    const wrapper = mount(FormTimePicker, {
        props: { modelValue: '', ...props },
        attrs: { 'aria-label': 'Hora de llegada' },
        global: { plugins: [i18n] },
        attachTo: document.body,
    });
    mounted.push(wrapper);

    return wrapper;
}

function emitted(wrapper: VueWrapper): unknown[] {
    return (wrapper.emitted('update:modelValue') ?? []).map((args) => args[0]);
}

afterEach(() => {
    mounted.splice(0).forEach((wrapper) => wrapper.unmount());
});

describe('FormTimePicker', () => {
    it('is a combobox named by the attributes it receives', () => {
        const input = mountPicker({ modelValue: '18:30' }).get('input');

        expect(input.attributes('role')).toBe('combobox');
        expect(input.attributes('aria-label')).toBe('Hora de llegada');
        expect(input.element.value).toBe('18:30');
    });

    it('turns a typed time into HH:MM and writes it back that way', async () => {
        const wrapper = mountPicker();
        const input = wrapper.get('input');

        await input.setValue('9h30');
        await input.trigger('blur');

        expect(emitted(wrapper).at(-1)).toBe('09:30');
        expect(input.element.value).toBe('09:30');
    });

    it('keeps what was typed and says so when it is not a time', async () => {
        const wrapper = mountPicker();
        const input = wrapper.get('input');

        await input.setValue('25:00');
        await input.trigger('blur');

        expect(emitted(wrapper).at(-1)).toBe('25:00');
        expect(input.attributes('aria-invalid')).toBe('true');
        expect(wrapper.text()).toContain('Escribe una hora como');
    });

    it('offers the day in steps and picks one with the keyboard', async () => {
        const wrapper = mountPicker({ step: 30 });
        const input = wrapper.get('input');

        await input.trigger('keydown', { key: 'ArrowDown' });
        expect(input.attributes('aria-expanded')).toBe('true');
        expect(wrapper.findAll('[role="option"]')).toHaveLength(48);

        await input.trigger('keydown', { key: 'ArrowDown' });
        await input.trigger('keydown', { key: 'Enter' });

        expect(emitted(wrapper).at(-1)).toBe('00:30');
        expect(input.attributes('aria-expanded')).toBe('false');
    });

    it('jumps to the first time that starts with what is typed', async () => {
        const wrapper = mountPicker({ step: 30 });
        const input = wrapper.get('input');

        await input.setValue('18');

        const active = input.attributes('aria-activedescendant');
        expect(active).toBeDefined();
        expect(wrapper.get(`#${active}`).text()).toBe('18:00');
    });

    it('picks a time with the pointer', async () => {
        const wrapper = mountPicker({ step: 60 });

        await wrapper.get('button[tabindex="-1"]').trigger('click');
        await wrapper.findAll('[role="option"]')[20]?.trigger('mousedown');

        expect(emitted(wrapper).at(-1)).toBe('20:00');
    });
});
