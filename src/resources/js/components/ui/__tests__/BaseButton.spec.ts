import { describe, expect, it } from 'vitest';
import { mount } from '@vue/test-utils';
import BaseButton from '../BaseButton.vue';

describe('BaseButton', () => {
    it('draws the primary variant from the theme button variables', () => {
        const classes = mount(BaseButton).classes();

        // Solid, outline or ghost is decided by the site setting through these variables
        expect(classes).toEqual(
            expect.arrayContaining([
                'bg-(--button-primary-bg)',
                'text-(--button-primary-text)',
                'inset-ring',
                'inset-ring-(--button-primary-border)',
                'hover:bg-(--button-primary-hover-bg)',
                'hover:text-(--button-primary-hover-text)',
            ])
        );
        expect(classes).not.toContain('bg-primary');
        expect(classes).not.toContain('text-white');
    });

    it.each(['secondary', 'danger', 'ghost'] as const)(
        'keeps the %s variant outside the button style setting',
        (variant) => {
            const classes = mount(BaseButton, { props: { variant } }).classes();

            expect(classes.some((name) => name.includes('--button-primary'))).toBe(false);
        }
    );
});
