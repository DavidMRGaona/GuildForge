import { afterEach, describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import FormDatePicker from '../FormDatePicker.vue';
import en from '@/locales/en';
import es from '@/locales/es';

const mounted: VueWrapper[] = [];

function mountPicker(props: Record<string, unknown> = {}, locale: 'es' | 'en' = 'es'): VueWrapper {
    const i18n = createI18n({ legacy: false, locale, messages: { es, en } });
    const wrapper = mount(FormDatePicker, {
        props: { modelValue: '', ...props },
        attrs: { 'aria-label': 'Fecha de la partida' },
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

describe('FormDatePicker', () => {
    it('shows the day in the order of the language', () => {
        const wrapper = mountPicker({ modelValue: '2026-03-07' });

        expect(wrapper.get('input').element.value).toBe('07/03/2026');
        expect(wrapper.get('input').attributes('aria-label')).toBe('Fecha de la partida');
    });

    it('turns a typed day into YYYY-MM-DD', async () => {
        const wrapper = mountPicker();

        await wrapper.get('input').setValue('7/3/2026');

        expect(emitted(wrapper).at(-1)).toBe('2026-03-07');
    });

    it('keeps what was typed and says so when it is not a day', async () => {
        const wrapper = mountPicker();
        const input = wrapper.get('input');

        await input.setValue('31/02/2026');
        await input.trigger('blur');

        expect(emitted(wrapper).at(-1)).toBe('31/02/2026');
        expect(input.attributes('aria-invalid')).toBe('true');
        expect(wrapper.text()).toContain('Escribe una fecha como');
    });

    it('opens a month and picks a day from it', async () => {
        const wrapper = mountPicker({ modelValue: '2026-03-07' });

        await wrapper.get('button[aria-haspopup="dialog"]').trigger('click');

        const dialog = wrapper.get('[role="dialog"]');
        expect(dialog.text()).toContain('Marzo de 2026');
        expect(document.activeElement?.getAttribute('data-day')).toBe('2026-03-07');

        await wrapper.get('[data-day="2026-03-19"]').trigger('click');

        expect(emitted(wrapper).at(-1)).toBe('2026-03-19');
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
    });

    it('moves through the days with the keyboard', async () => {
        const wrapper = mountPicker({ modelValue: '2026-03-31' });
        await wrapper.get('button[aria-haspopup="dialog"]').trigger('click');

        await wrapper.get('[data-day="2026-03-31"]').trigger('keydown', { key: 'ArrowRight' });
        expect(document.activeElement?.getAttribute('data-day')).toBe('2026-04-01');
        expect(wrapper.get('[role="dialog"]').text()).toContain('Abril de 2026');

        await wrapper.get('[data-day="2026-04-01"]').trigger('keydown', { key: 'ArrowUp' });
        expect(document.activeElement?.getAttribute('data-day')).toBe('2026-03-25');

        await wrapper.get('[data-day="2026-03-25"]').trigger('keydown', { key: 'PageDown' });
        expect(document.activeElement?.getAttribute('data-day')).toBe('2026-04-25');

        await wrapper.get('[data-day="2026-04-25"]').trigger('keydown', { key: 'Escape' });
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);
        expect(emitted(wrapper)).toEqual([]);
    });

    it('starts weeks on Monday in Spanish', async () => {
        const wrapper = mountPicker({ modelValue: '2026-11-15' });
        await wrapper.get('button[aria-haspopup="dialog"]').trigger('click');

        const headers = wrapper.findAll('th').map((header) => header.attributes('abbr'));

        expect(headers[0]).toBe('lunes');
    });

    it('does not offer days outside min and max', async () => {
        const wrapper = mountPicker({ modelValue: '2026-03-07', min: '2026-03-05' });
        await wrapper.get('button[aria-haspopup="dialog"]').trigger('click');

        expect(wrapper.get('[data-day="2026-03-04"]').attributes('disabled')).toBeDefined();
        expect(wrapper.get('[data-day="2026-03-05"]').attributes('disabled')).toBeUndefined();
    });

    it('names its buttons in the current language', async () => {
        const wrapper = mountPicker({}, 'en');

        expect(wrapper.get('button[aria-haspopup="dialog"]').attributes('aria-label')).toBe(
            'Choose a date'
        );
        expect(wrapper.get('input').attributes('placeholder')).toBe('mm/dd/yyyy');
    });
});
