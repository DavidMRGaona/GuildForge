import { beforeEach, describe, expect, it, vi } from 'vitest';
import { computed, nextTick, watch } from 'vue';

type Contract = typeof import('../cookieConsent');

// Shared prop `cookieConsentModule`: whether an enabled module declares itself the provider
const pageProps: { cookieConsentModule?: boolean } = {};

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): { props: typeof pageProps } => ({ props: pageProps }),
}));

// The contract keeps the provider in module state: every test loads a fresh copy
let contract: Contract;

beforeEach(async () => {
    vi.useRealTimers();
    pageProps.cookieConsentModule = false;
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

    describe('decision', () => {
        function decidingProvider(): { decide: () => void } {
            let decided = false;
            contract.registerCookieConsentProvider({
                hasConsent: (): boolean => false,
                hasDecided: (): boolean => decided,
            });

            return {
                decide: (): void => {
                    decided = true;
                    contract.notifyCookieConsentDecided();
                },
            };
        }

        it('tells whether the page has a consent module from the shared prop', () => {
            expect(contract.hasCookieConsentModule()).toBe(false);

            pageProps.cookieConsentModule = true;

            expect(contract.hasCookieConsentModule()).toBe(true);
        });

        it('reports whether the visitor already answered', () => {
            const { decide } = decidingProvider();
            expect(contract.hasCookieConsentDecided()).toBe(false);

            decide();

            expect(contract.hasCookieConsentDecided()).toBe(true);
        });

        it('treats a missing provider as not decided', () => {
            expect(contract.hasCookieConsentDecided()).toBe(false);
        });

        it('treats a provider without hasDecided() as decided, so it never blocks', () => {
            consentTo('preferences');

            expect(contract.hasCookieConsentDecided()).toBe(true);
        });

        it('treats a failing hasDecided() as not decided', () => {
            contract.registerCookieConsentProvider({
                hasConsent: (): boolean => false,
                hasDecided: (): boolean => {
                    throw new Error('Broken provider');
                },
            });

            expect(contract.hasCookieConsentDecided()).toBe(false);
        });

        it('does not run decision listeners on a plain change notice, even after deciding', () => {
            const listener = vi.fn();
            const { decide } = decidingProvider();
            decide();
            contract.onCookieConsentDecided(listener);

            // The banner's watcher fires on every page visit
            contract.notifyCookieConsentChanged();
            contract.notifyCookieConsentChanged();

            expect(listener).not.toHaveBeenCalled();
        });

        it('notifies listeners when the provider reports a decision', () => {
            const listener = vi.fn();
            const { decide } = decidingProvider();
            contract.onCookieConsentDecided(listener);

            decide();

            expect(listener).toHaveBeenCalledTimes(1);
        });

        it('does not notify before a decision, and stops after unsubscribing', () => {
            const listener = vi.fn();
            const { decide } = decidingProvider();
            const stop = contract.onCookieConsentDecided(listener);

            expect(listener).not.toHaveBeenCalled();

            stop();
            decide();
            expect(listener).not.toHaveBeenCalled();
        });

        it('keeps notifying the other listeners when one throws', () => {
            const second = vi.fn();
            const { decide } = decidingProvider();
            contract.onCookieConsentDecided(() => {
                throw new Error('Broken listener');
            });
            contract.onCookieConsentDecided(second);

            decide();

            expect(second).toHaveBeenCalledTimes(1);
        });
    });

    describe('whenConsentDecided', () => {
        it('resolves at once when no consent module is active', async () => {
            await expect(contract.whenConsentDecided()).resolves.toBeUndefined();
        });

        it('resolves at once when the visitor already answered', async () => {
            pageProps.cookieConsentModule = true;
            contract.registerCookieConsentProvider({
                hasConsent: (): boolean => true,
                hasDecided: (): boolean => true,
            });

            await expect(contract.whenConsentDecided()).resolves.toBeUndefined();
        });

        it('waits for the decision', async () => {
            pageProps.cookieConsentModule = true;
            let decided = false;
            contract.registerCookieConsentProvider({
                hasConsent: (): boolean => false,
                hasDecided: (): boolean => decided,
            });
            const settled = vi.fn();
            void contract.whenConsentDecided().then(settled);

            await Promise.resolve();
            expect(settled).not.toHaveBeenCalled();

            decided = true;
            contract.notifyCookieConsentChanged();
            await Promise.resolve();

            expect(settled).toHaveBeenCalledTimes(1);
        });

        it('waits for a provider that registers late', async () => {
            pageProps.cookieConsentModule = true;
            const settled = vi.fn();
            void contract.whenConsentDecided().then(settled);

            await Promise.resolve();
            expect(settled).not.toHaveBeenCalled();

            contract.registerCookieConsentProvider({
                hasConsent: (): boolean => false,
                hasDecided: (): boolean => true,
            });
            await Promise.resolve();

            expect(settled).toHaveBeenCalledTimes(1);
        });

        it('gives up when the provider never registers', async () => {
            vi.useFakeTimers();
            pageProps.cookieConsentModule = true;
            const settled = vi.fn();
            void contract.whenConsentDecided().then(settled);

            await vi.advanceTimersByTimeAsync(contract.CONSENT_PROVIDER_TIMEOUT_MS - 1);
            expect(settled).not.toHaveBeenCalled();

            await vi.advanceTimersByTimeAsync(1);
            expect(settled).toHaveBeenCalledTimes(1);
        });

        it('waits at least as long as a lazy slot component may take to load', async () => {
            const { SLOT_COMPONENT_TIMEOUT_MS } = await import('@/utils/moduleLoadTimeout');

            expect(contract.CONSENT_PROVIDER_TIMEOUT_MS).toBeGreaterThan(SLOT_COMPONENT_TIMEOUT_MS);
        });

        it('lets a provider that arrives after the time limit be asked again', async () => {
            vi.useFakeTimers();
            pageProps.cookieConsentModule = true;
            void contract.whenConsentDecided();
            await vi.advanceTimersByTimeAsync(contract.CONSENT_PROVIDER_TIMEOUT_MS);

            contract.registerCookieConsentProvider({
                hasConsent: (): boolean => false,
                hasDecided: (): boolean => false,
            });

            // The caller can tell the banner is waiting and close what it opened
            expect(contract.hasCookieConsentDecided()).toBe(false);
            const settled = vi.fn();
            void contract.whenConsentDecided().then(settled);
            await vi.advanceTimersByTimeAsync(contract.CONSENT_PROVIDER_TIMEOUT_MS * 2);
            expect(settled).not.toHaveBeenCalled();
        });

        it('waits for a late provider whose banner is still open, then for the decision', async () => {
            pageProps.cookieConsentModule = true;
            let decided = false;
            const settled = vi.fn();
            void contract.whenConsentDecided().then(settled);

            contract.registerCookieConsentProvider({
                hasConsent: (): boolean => false,
                hasDecided: (): boolean => decided,
            });
            await Promise.resolve();
            expect(settled).not.toHaveBeenCalled();

            decided = true;
            contract.notifyCookieConsentDecided();
            await Promise.resolve();

            expect(settled).toHaveBeenCalledTimes(1);
        });

        it('settles every caller waiting at the same time', async () => {
            pageProps.cookieConsentModule = true;
            let decided = false;
            contract.registerCookieConsentProvider({
                hasConsent: (): boolean => false,
                hasDecided: (): boolean => decided,
            });
            const settled = vi.fn();
            void contract.whenConsentDecided().then(settled);
            void contract.whenConsentDecided().then(settled);
            void contract.whenConsentDecided().then(settled);

            decided = true;
            contract.notifyCookieConsentChanged();
            await Promise.resolve();

            expect(settled).toHaveBeenCalledTimes(3);
        });

        it('rejects with the signal reason when the wait is cancelled', async () => {
            pageProps.cookieConsentModule = true;
            contract.registerCookieConsentProvider({
                hasConsent: (): boolean => false,
                hasDecided: (): boolean => false,
            });
            const controller = new AbortController();
            const waiting = contract.whenConsentDecided({ signal: controller.signal });

            controller.abort(new Error('Unmounted'));

            await expect(waiting).rejects.toThrow('Unmounted');
        });

        it('rejects at once with a signal that is already aborted', async () => {
            const controller = new AbortController();
            controller.abort(new Error('Unmounted'));

            await expect(
                contract.whenConsentDecided({ signal: controller.signal })
            ).rejects.toThrow('Unmounted');
        });

        it('stops listening and timing out once cancelled', async () => {
            vi.useFakeTimers();
            pageProps.cookieConsentModule = true;
            const controller = new AbortController();
            const waiting = contract.whenConsentDecided({ signal: controller.signal });
            controller.abort(new Error('Unmounted'));
            await expect(waiting).rejects.toThrow('Unmounted');

            expect(vi.getTimerCount()).toBe(0);
        });

        it('does not time out while the banner is waiting for an answer', async () => {
            vi.useFakeTimers();
            pageProps.cookieConsentModule = true;
            contract.registerCookieConsentProvider({
                hasConsent: (): boolean => false,
                hasDecided: (): boolean => false,
            });
            const settled = vi.fn();
            void contract.whenConsentDecided().then(settled);

            await vi.advanceTimersByTimeAsync(contract.CONSENT_PROVIDER_TIMEOUT_MS * 10);

            expect(settled).not.toHaveBeenCalled();
        });
    });
});
