import { usePage } from '@inertiajs/vue3';
import { shallowRef } from 'vue';

/**
 * Cookie consent contract between modules.
 *
 * A cookie consent module registers itself as the provider; any other module asks it before
 * reading or writing a cookie that needs consent. This file is an import map entry
 * (`@/contracts/cookieConsent`) and module builds keep `@/contracts/*` external, so the page
 * loads it once and every bundle shares this module state.
 */
export interface CookieConsentProvider {
    /** Whether the visitor consented to the category. */
    hasConsent(categorySlug: string): boolean;
    /**
     * Whether the visitor already answered the banner (accepted, rejected or saved preferences).
     * Optional so that providers written before it keep working: without it the contract treats
     * the visitor as decided, so nothing waits for an answer the provider cannot report.
     */
    hasDecided?(): boolean;
}

/** Called after the visitor answers the banner. */
export type CookieConsentDecidedListener = () => void;

/**
 * How long `whenConsentDecided()` waits for a provider to register before giving up. The page
 * declares a consent module (shared prop `cookieConsentModule`), whose bundle loads after the
 * page; if it never registers (failed to load, blocked), waiting longer would hold back the
 * caller forever. Once a provider registers there is no limit: the banner waits for the visitor.
 */
export const CONSENT_PROVIDER_TIMEOUT_MS = 5000;

let provider: CookieConsentProvider | null = null;

const decidedListeners = new Set<CookieConsentDecidedListener>();
// Run on every registration and notification, to settle the callers that wait
const changeListeners = new Set<() => void>();

// Bumped on every change, so that reading consent inside a computed, watcher or render follows it
const revision = shallowRef(0);

export function registerCookieConsentProvider(next: CookieConsentProvider): void {
    provider = next;
    revision.value += 1;
    runAll(changeListeners);
}

/** Called by the provider whenever the visitor's choice changes. */
export function notifyCookieConsentChanged(): void {
    revision.value += 1;
    runAll(changeListeners);

    // One call after each answer: accept, reject or saved preferences
    if (hasCookieConsentDecided()) {
        runAll(decidedListeners);
    }
}

function runAll(listeners: ReadonlySet<() => void>): void {
    for (const listener of [...listeners]) {
        try {
            listener();
        } catch {
            // A broken listener never stops the others
        }
    }
}

/**
 * Whether the visitor consented to the category. Reactive: computed values, watchers and
 * templates that call it run again when a provider registers or consent changes.
 * Without a provider, or when the provider fails, the answer is no consent.
 */
export function hasCookieConsent(categorySlug: string): boolean {
    // Reading the revision is what makes the caller track consent changes
    void revision.value;

    if (provider === null) {
        return false;
    }

    try {
        return provider.hasConsent(categorySlug) === true;
    } catch {
        return false;
    }
}

/**
 * Whether an enabled module declares itself the consent provider (shared prop
 * `cookieConsentModule`, set by the server whatever the module is called). False means there is
 * no banner to wait for; true does not mean the provider has loaded yet.
 */
export function hasCookieConsentModule(): boolean {
    return usePage().props.cookieConsentModule === true;
}

/**
 * Whether the visitor already answered the banner. Reactive like `hasCookieConsent()`.
 * False without a provider or when it fails; true for a provider that cannot tell
 * (no `hasDecided()`), so it never blocks.
 */
export function hasCookieConsentDecided(): boolean {
    void revision.value;

    if (provider === null) {
        return false;
    }

    if (typeof provider.hasDecided !== 'function') {
        return true;
    }

    try {
        return provider.hasDecided() === true;
    } catch {
        return false;
    }
}

/**
 * Runs the listener each time the visitor answers the banner (accepting, rejecting or saving
 * preferences, including a later change of mind). Returns the function that unsubscribes.
 */
export function onCookieConsentDecided(listener: CookieConsentDecidedListener): () => void {
    decidedListeners.add(listener);

    return (): void => {
        decidedListeners.delete(listener);
    };
}

/**
 * Resolves once the visitor has answered the banner, and at once when there is nothing to wait
 * for: no consent module on the page, or an answer already given. If the module is declared but
 * its provider does not register within `CONSENT_PROVIDER_TIMEOUT_MS` it resolves too, so a
 * module that failed to load never holds the caller back. Never rejects.
 */
export function whenConsentDecided(): Promise<void> {
    if (!hasCookieConsentModule() || hasCookieConsentDecided()) {
        return Promise.resolve();
    }

    return new Promise<void>((resolve) => {
        let timer: ReturnType<typeof setTimeout> | null = null;

        const finish = (): void => {
            if (timer !== null) {
                clearTimeout(timer);
            }
            changeListeners.delete(check);
            resolve();
        };

        const check = (): void => {
            if (provider === null) {
                return;
            }

            // A provider is here: the visitor decides when, with no time limit
            if (timer !== null) {
                clearTimeout(timer);
                timer = null;
            }

            if (hasCookieConsentDecided()) {
                finish();
            }
        };

        changeListeners.add(check);

        if (provider === null) {
            timer = setTimeout(finish, CONSENT_PROVIDER_TIMEOUT_MS);
        }
    });
}
