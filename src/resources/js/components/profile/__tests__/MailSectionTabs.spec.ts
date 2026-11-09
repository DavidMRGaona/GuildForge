import { describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import MailSectionTabs from '../MailSectionTabs.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type { MailDelivery, MailPreference, MailPreferenceSection } from '@/types/mailPreferences';

function category(key: string, mandatory = false, enabled = true): MailPreference {
    return {
        key,
        label: key,
        description: key,
        mandatory,
        mandatoryReason: mandatory ? 'Siempre se envía' : null,
        enabled,
        digestible: false,
        delivery: enabled ? 'immediate' : 'none',
    };
}

const SECTIONS: MailPreferenceSection[] = [
    { key: 'Cuenta', label: 'Cuenta', icon: null, categories: [category('c.a', true)] },
    {
        key: 'Partidas',
        label: 'Partidas',
        icon: null,
        categories: [category('p.one'), category('p.two', false, false), category('p.three')],
    },
    { key: 'Torneos', label: 'Torneos', icon: null, categories: [category('t.one')] },
];

function mountTabs(choices: Record<string, MailDelivery> = {}, modelValue = 'Cuenta'): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(MailSectionTabs, {
        props: { sections: SECTIONS, choices, modelValue, panelId: 'panel' },
        global: { plugins: [i18n] },
        attachTo: document.body,
    });
}

describe('MailSectionTabs', () => {
    it('is a tab list with one tab per section, the selected one reachable by Tab', () => {
        const wrapper = mountTabs({}, 'Partidas');

        expect(wrapper.get('[role="tablist"]').attributes('aria-label')).toBe(
            'Secciones de correo'
        );
        const tabs = wrapper.findAll('[role="tab"]');
        expect(tabs.map((tab) => tab.attributes('aria-selected'))).toEqual([
            'false',
            'true',
            'false',
        ]);
        expect(tabs.map((tab) => tab.attributes('tabindex'))).toEqual(['-1', '0', '-1']);
        expect(tabs.every((tab) => tab.attributes('aria-controls') === 'panel')).toBe(true);
    });

    it('shows how many optional categories are on, read once by screen readers', () => {
        const wrapper = mountTabs({ 'p.one': 'none' });

        const tab = wrapper.get('#mail-tab-Partidas');
        expect(tab.get('[aria-hidden="true"]').text()).toBe('1/3');
        expect(tab.get('.sr-only').text()).toBe('1 de 3 activados');
    });

    it('shows no count for a section with only mandatory categories', () => {
        const wrapper = mountTabs();

        expect(wrapper.get('#mail-tab-Cuenta').text()).toBe('Cuenta');
    });

    it('selects a tab on click', async () => {
        const wrapper = mountTabs();

        await wrapper.get('#mail-tab-Torneos').trigger('click');

        expect(wrapper.emitted('update:modelValue')).toEqual([['Torneos']]);
    });

    it('moves with the arrow keys, Home and End, wrapping around', async () => {
        const wrapper = mountTabs({}, 'Cuenta');
        const tab = wrapper.get('#mail-tab-Cuenta');

        await tab.trigger('keydown', { key: 'ArrowRight' });
        await tab.trigger('keydown', { key: 'ArrowLeft' });
        await tab.trigger('keydown', { key: 'End' });
        await wrapper.get('#mail-tab-Torneos').trigger('keydown', { key: 'Home' });
        await tab.trigger('keydown', { key: 'a' });

        expect(wrapper.emitted('update:modelValue')).toEqual([
            ['Partidas'],
            ['Torneos'],
            ['Torneos'],
            ['Cuenta'],
        ]);
    });
});
