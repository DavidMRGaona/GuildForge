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

    it('draws the outline variant as a border on a transparent background', () => {
        const classes = mount(BaseButton, { props: { variant: 'outline' } }).classes();

        // An inset ring, like the primary border: same size as the solid buttons beside it
        expect(classes).toEqual(
            expect.arrayContaining([
                'bg-transparent',
                'text-base-primary',
                'inset-ring',
                'inset-ring-(--color-border)',
                'hover:bg-muted',
            ])
        );
        expect(classes).not.toContain('bg-muted');
    });

    it.each(['secondary', 'danger', 'ghost', 'outline'] as const)(
        'keeps the %s variant outside the button style setting',
        (variant) => {
            const classes = mount(BaseButton, { props: { variant } }).classes();

            expect(classes.some((name) => name.includes('--button-primary'))).toBe(false);
        }
    );
});
