import { describe, expect, it } from 'vitest';
import { isInertiaPage } from '../inertiaLinks';

describe('isInertiaPage', () => {
    it('is true for pages of the site', () => {
        for (const href of [
            '/',
            '/eventos',
            '/eventos/noche-de-rol?x=1#a',
            '/adminístrate',
            '/upcoming',
        ]) {
            expect(isInertiaPage(href), href).toBe(true);
        }
    });

    it('is false for the panel, framework endpoints and files', () => {
        for (const href of [
            '/admin',
            '/admin/',
            '/ADMIN/users',
            '/up',
            '/up?x=1',
            '/livewire/update',
            '/storage/x',
            '/normas.pdf',
            '/a/b.PNG?v=1',
            '/feed.xml',
        ]) {
            expect(isInertiaPage(href), href).toBe(false);
        }
    });

    it('is false for addresses that are not a path of the site', () => {
        for (const href of [
            'https://example.com/eventos',
            'mailto:a@example.com',
            '//example.com',
            'eventos',
            '',
        ]) {
            expect(isInertiaPage(href), href).toBe(false);
        }
    });
});
