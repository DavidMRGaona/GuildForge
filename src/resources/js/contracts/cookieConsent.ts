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
}

let provider: CookieConsentProvider | null = null;

// Bumped on every change, so that reading consent inside a computed, watcher or render follows it
const revision = shallowRef(0);

export function registerCookieConsentProvider(next: CookieConsentProvider): void {
    provider = next;
    revision.value += 1;
}

/** Called by the provider whenever the visitor's choice changes. */
export function notifyCookieConsentChanged(): void {
    revision.value += 1;
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
