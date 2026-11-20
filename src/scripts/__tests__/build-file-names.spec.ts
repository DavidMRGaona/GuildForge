import { describe, expect, it } from 'vitest';
import { BLOCKED_NAME_PATTERN, blockerSafeName, jsFileName } from '../build-file-names.mjs';

describe('blockerSafeName', () => {
    it.each([
        'cookieConsent',
        'CookieBanner',
        'resources/js/components/CookieBanner.vue',
        'useConsent',
        'cookie-consent',
        'cookie_settings',
        'gdprNotice',
    ])('gives %s a neutral name', (name) => {
        const safe = blockerSafeName(name);

        expect(safe).toBe('choices');
        expect(BLOCKED_NAME_PATTERN.test(safe)).toBe(false);
    });

    it.each([
        'app',
        'vendor-vue',
        'useFlashMessages',
        'resources/js/components/RegistrationButton.vue',
        'Unsubscribe',
    ])('keeps %s as it is', (name) => {
        expect(blockerSafeName(name)).toBe(name);
    });
});

describe('jsFileName', () => {
    it('names a chunk after its blocker-safe name and its hash', () => {
        expect(jsFileName({ name: 'cookieConsent' })).toBe('assets/choices-[hash].js');
        expect(jsFileName({ name: 'vendor-vue' })).toBe('assets/vendor-vue-[hash].js');
    });
});
