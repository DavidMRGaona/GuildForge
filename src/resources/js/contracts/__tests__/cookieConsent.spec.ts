import { beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, nextTick, watch } from 'vue';

type Contract = typeof import('../cookieConsent');

// The contract keeps the provider in module state: every test loads a fresh copy
let contract: Contract;

beforeEach(async () => {
    vi.resetModules();
    contract = await import('../cookieConsent');
});

function consentTo(...granted: string[]): void {
    contract.registerCookieConsentProvider({
        hasConsent: (categorySlug: string): boolean => granted.includes(categorySlug),
    });
}

describe('cookie consent contract', () => {
    it('answers no consent while no consent module is on the page', () => {
        expect(contract.hasCookieConsent('necessary')).toBe(false);
        expect(contract.hasCookieConsent('preferences')).toBe(false);
    });

    it('asks the registered provider', () => {
        consentTo('preferences');

        expect(contract.hasCookieConsent('preferences')).toBe(true);
        expect(contract.hasCookieConsent('analytics')).toBe(false);
    });

    it('answers no consent when the provider fails', () => {
        contract.registerCookieConsentProvider({
            hasConsent: (): boolean => {
                throw new Error('Broken provider');
            },
        });

        expect(contract.hasCookieConsent('preferences')).toBe(false);
    });

    it('lets a later provider replace the earlier one', () => {
        consentTo('preferences');
        consentTo();

        expect(contract.hasCookieConsent('preferences')).toBe(false);
    });

    it('updates a computed value when a provider registers', () => {
        const allowed = computed(() => contract.hasCookieConsent('preferences'));
        expect(allowed.value).toBe(false);

        consentTo('preferences');

        expect(allowed.value).toBe(true);
    });

    it('updates a computed value when the provider reports a change', () => {
        let granted = false;
        contract.registerCookieConsentProvider({ hasConsent: (): boolean => granted });
        const allowed = computed(() => contract.hasCookieConsent('preferences'));
        expect(allowed.value).toBe(false);

        granted = true;
        expect(allowed.value).toBe(false);

        contract.notifyCookieConsentChanged();
        expect(allowed.value).toBe(true);
    });

    it('runs a watcher when consent changes', async () => {
        const changes = vi.fn();
        watch(() => contract.hasCookieConsent('preferences'), changes);

        consentTo('preferences');
        await nextTick();

        expect(changes).toHaveBeenCalledWith(true, false, expect.anything());
    });
});
