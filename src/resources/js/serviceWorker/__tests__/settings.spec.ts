import { describe, expect, it } from 'vitest';
import { readSettings } from '../settings';

const ORIGIN = 'https://example.test';

describe('readSettings', () => {
    it('keeps the settings the server wrote before the worker', () => {
        expect(
            readSettings(
                {
                    fallbackTitle: 'Tienes un aviso nuevo',
                    fallbackBody: 'Asociación de ejemplo',
                    fallbackPath: '/avisos',
                    icon: '/favicons/light/android-chrome-192x192.png',
                },
                ORIGIN
            )
        ).toEqual({
            fallbackTitle: 'Tienes un aviso nuevo',
            fallbackBody: 'Asociación de ejemplo',
            fallbackPath: '/avisos',
            icon: '/favicons/light/android-chrome-192x192.png',
        });
    });

    it('still gives something to show when the settings are missing', () => {
        expect(readSettings(undefined, ORIGIN)).toEqual({
            fallbackTitle: 'example.test',
            fallbackBody: '',
            fallbackPath: '/',
            icon: '',
        });
    });

    it('never sends anyone to another site from the fallback path', () => {
        for (const fallbackPath of [
            'https://evil.test/avisos',
            '//evil.test/avisos',
            'javascript:alert(1)',
            '/\\evil.test/avisos',
        ]) {
            expect(readSettings({ fallbackPath }, ORIGIN).fallbackPath).toBe('/');
        }
    });

    it('ignores values of the wrong type', () => {
        const settings = readSettings({ fallbackTitle: 3, fallbackBody: null, icon: {} }, ORIGIN);

        expect(settings.fallbackTitle).toBe('example.test');
        expect(settings.fallbackBody).toBe('');
        expect(settings.icon).toBe('');
    });
});
