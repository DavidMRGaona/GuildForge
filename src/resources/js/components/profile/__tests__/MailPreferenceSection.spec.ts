import { describe, expect, it } from 'vitest';
import { mount, type VueWrapper } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import MailPreferenceSection from '../MailPreferenceSection.vue';
import en from '@/locales/en';
import es from '@/locales/es';
import type {
    MailDelivery,
    MailPreference,
    MailPreferenceSection as Section,
} from '@/types/mailPreferences';

function category(key: string, overrides: Partial<MailPreference> = {}): MailPreference {
    return {
        key,
        label: `Etiqueta ${key}`,
        description: `Descripción ${key}`,
        mandatory: false,
        mandatoryReason: null,
        enabled: true,
        digestible: false,
        delivery: overrides.enabled === false ? 'none' : 'immediate',
        ...overrides,
    };
}

function mandatory(key: string): MailPreference {
    return category(key, {
        mandatory: true,
        mandatoryReason: `Siempre se envía: motivo ${key}`,
    });
}

function mountSection(
    section: Partial<Section> & Pick<Section, 'categories'>,
    choices: Record<string, MailDelivery> = {}
): VueWrapper {
    const i18n = createI18n({ legacy: false, locale: 'es', messages: { es, en } });

    return mount(MailPreferenceSection, {
        props: { section: { key: 'Partidas', label: 'Partidas', icon: null, ...section }, choices },
        global: { plugins: [i18n] },
    });
}

describe('MailPreferenceSection', () => {
    it('is a card with one heading that names it', () => {
        const wrapper = mountSection({ categories: [category('a.one')] });

        expect(wrapper.findAll('h3')).toHaveLength(1);
        const section = wrapper.find('section');
        const heading = wrapper.find(`#${section.attributes('aria-labelledby') ?? ''}`);
        expect(heading.element.tagName).toBe('H3');
        expect(heading.text()).toBe('Partidas');
        expect(section.classes()).toEqual(expect.arrayContaining(['border', 'bg-surface']));
    });

    it('summarises how many optional categories are on, following the current choices', () => {
        const categories = [
            category('a.one'),
            category('a.two', { enabled: false }),
            category('a.three'),
            mandatory('a.locked'),
        ];

        expect(
            mountSection({ categories }).get('[data-testid="mail-section-summary"]').text()
        ).toBe('2 de 3 activados');
        expect(
            mountSection({ categories }, { 'a.one': 'none', 'a.two': 'immediate' })
                .get('[data-testid="mail-section-summary"]')
                .text()
        ).toBe('2 de 3 activados');
        expect(
            mountSection({ categories }, { 'a.one': 'none' })
                .get('[data-testid="mail-section-summary"]')
                .text()
        ).toBe('1 de 3 activados');
    });

    it('uses the singular for a single optional category', () => {
        const wrapper = mountSection({ categories: [category('a.one')] });

        expect(wrapper.get('[data-testid="mail-section-summary"]').text()).toBe('1 de 1 activado');
    });

    it('reads the summary once: it is plain text outside the heading', () => {
        const wrapper = mountSection({ categories: [category('a.one')] });

        expect(wrapper.get('h3').text()).toBe('Partidas');
        expect(
            wrapper.get('[data-testid="mail-section-summary"]').attributes('aria-label')
        ).toBeUndefined();
        expect(wrapper.find('[aria-live]').exists()).toBe(false);
    });

    it('puts the mandatory categories after the optional ones, as a labelled list with their reason', () => {
        const wrapper = mountSection({
            categories: [mandatory('a.locked'), category('a.one')],
        });

        const list = wrapper.get('ul[aria-label="Siempre se envían"]');
        expect(list.findAll('li')).toHaveLength(1);
        expect(list.text()).toContain('Siempre se envía: motivo a.locked');
        expect(list.find('[role="switch"]').exists()).toBe(false);
        expect(wrapper.findAll('[role="switch"]')).toHaveLength(1);
        expect(
            wrapper.get('[data-category="a.one"]').element.compareDocumentPosition(list.element)
        ).toBe(Node.DOCUMENT_POSITION_FOLLOWING);
    });

    it('shows only the mandatory part for a section without optional categories', () => {
        const wrapper = mountSection({ categories: [mandatory('a.locked')] });

        expect(wrapper.find('[data-testid="mail-section-summary"]').exists()).toBe(false);
        expect(wrapper.find('[data-category]').exists()).toBe(false);
        expect(wrapper.find('[role="switch"]').exists()).toBe(false);
        expect(wrapper.find('ul[aria-label="Siempre se envían"]').exists()).toBe(true);
    });

    it('shows no mandatory block when everything can be changed', () => {
        const wrapper = mountSection({ categories: [category('a.one')] });

        expect(wrapper.find('ul[aria-label="Siempre se envían"]').exists()).toBe(false);
    });

    it('tells the parent which category changed', async () => {
        const wrapper = mountSection({ categories: [category('a.one')] });

        await wrapper.get('[data-category="a.one"] [role="switch"]').trigger('click');

        expect(wrapper.emitted('change')).toEqual([['a.one', 'none']]);
    });

    it('counts a category received in the daily digest as on', () => {
        const categories = [
            category('a.one', { digestible: true, delivery: 'digest' }),
            category('a.two', { enabled: false }),
        ];

        expect(
            mountSection({ categories }).get('[data-testid="mail-section-summary"]').text()
        ).toBe('1 de 2 activados');
        expect(
            mountSection({ categories }, { 'a.one': 'none' })
                .get('[data-testid="mail-section-summary"]')
                .text()
        ).toBe('0 de 2 activados');
    });

    it('offers three choices instead of the switch where the daily digest is possible', async () => {
        const wrapper = mountSection({
            categories: [category('a.one', { digestible: true, delivery: 'immediate' })],
        });

        const row = wrapper.get('[data-category="a.one"]');
        expect(row.find('[role="switch"]').exists()).toBe(false);
        const fieldset = row.get('fieldset');
        expect(wrapper.get(`#${fieldset.attributes('aria-labelledby') ?? ''}`).text()).toBe(
            'Etiqueta a.one'
        );
        await row.get('input[value="digest"]').trigger('change');

        expect(wrapper.emitted('change')).toEqual([['a.one', 'digest']]);
    });

    it('draws the icon a module names and falls back to a mail icon for an unknown one', () => {
        const known = mountSection({ icon: 'dices', categories: [category('a.one')] });
        const unknown = mountSection({ icon: 'not-an-icon', categories: [category('a.one')] });
        const none = mountSection({ icon: null, categories: [category('a.one')] });

        expect(known.get('header svg').classes()).toContain('lucide-dices');
        expect(unknown.get('header svg').classes()).toContain('lucide-mail');
        expect(none.get('header svg').classes()).toContain('lucide-mail');
        expect(known.get('header svg').attributes('aria-hidden')).toBe('true');
    });
});
