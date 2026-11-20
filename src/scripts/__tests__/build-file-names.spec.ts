import { describe, expect, it } from 'vitest';
import { HASH_CHARACTERS, jsFileName, PUBLISHED_SCRIPT } from '../build-file-names.mjs';

describe('jsFileName', () => {
    it.each([
        'cookieConsent',
        'ShareButton',
        'useReadTracker',
        'resources/js/pages/Newsletter/Subscribe.vue',
        'resources/js/components/RegistrationButton.vue',
        'app',
    ])('publishes %s under its hash alone', (name) => {
        expect(jsFileName({ name })).toBe('assets/[hash].js');
    });

    it.each(['vendor-vue', 'vendor-inertia', 'vendor-head', 'vendor-calendar'])(
        'keeps the name of the shared group %s',
        (name) => {
            expect(jsFileName({ name })).toBe(`assets/${name}-[hash].js`);
        }
    );
});

describe('PUBLISHED_SCRIPT', () => {
    it.each(['assets/k3j9x0ab.js', 'assets/vendor-vue-k3j9x0ab.js'])('accepts %s', (file) => {
        expect(PUBLISHED_SCRIPT.test(file)).toBe(true);
    });

    it.each([
        'assets/cookieConsent-BqD-Wubj.js',
        'assets/ShareButton-k3j9x0ab.js',
        'assets/resources/js/pages/Newsletter/Subscribe.vue-k3j9x0ab.js',
        'assets/choices-k3j9x0ab.js',
        'assets/k3j-x0ab.js',
        'assets/K3J9X0AB.js',
    ])('refuses %s', (file) => {
        expect(PUBLISHED_SCRIPT.test(file)).toBe(false);
    });
});

describe('HASH_CHARACTERS', () => {
    it('leaves out the separators block lists match words between', () => {
        expect(HASH_CHARACTERS).toBe('base36');
    });
});
