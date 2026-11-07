import { describe, expect, it } from 'vitest';
import { defineComponent } from 'vue';
import { mount } from '@vue/test-utils';
import { createI18n } from 'vue-i18n';
import { useEvents } from '../useEvents';
import en from '@/locales/en';
import es from '@/locales/es';

function formatPriceIn(locale: 'es' | 'en', price: number | null): string {
    let formatted = '';
    const Harness = defineComponent({
        setup() {
            formatted = useEvents().formatPrice(price);
            return {};
        },
        template: '<div />',
    });

    mount(Harness, {
        global: { plugins: [createI18n({ legacy: false, locale, messages: { es, en } })] },
    });

    // Intl separates the amount and the symbol with a no-break space
    return formatted.replace(/\s/g, ' ');
}

describe('useEvents().formatPrice', () => {
    it('uses a decimal comma in Spanish', () => {
        expect(formatPriceIn('es', 23.6)).toBe('23,60 €');
    });

    it('follows the English format in English', () => {
        expect(formatPriceIn('en', 23.6)).toBe('€23.60');
    });

    it('says the event is free when there is no price', () => {
        expect(formatPriceIn('es', null)).toBe('Gratuito');
    });
});
