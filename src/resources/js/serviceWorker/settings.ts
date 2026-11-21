/**
 * What the server writes before the worker in `/sw.js` (`self.serviceWorkerSettings`, core#802):
 * the worker has no i18n and no page props, so the site's texts, icon and notices path come this way.
 *
 * Kept free of worker types: the page's tests import it too.
 */
export interface ServiceWorkerSettings {
    /** Title of a notification whose notice cannot be read («Tienes un aviso nuevo»). */
    fallbackTitle: string;
    /** Its text: the site's name. */
    fallbackBody: string;
    /** Where it leads: the list of notices. */
    fallbackPath: string;
    /** Icon of every notification, empty when there is none. */
    icon: string;
    /** Title of the test notice sent from the «Avisos» tab (core#804). */
    testTitle: string;
    /** Its text. */
    testBody: string;
    /** Where it leads: the «Avisos» tab. */
    testPath: string;
}

function text(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

/**
 * A path on this site, never an address elsewhere (`//host`, `/\host`, another scheme): `/` otherwise.
 */
export function sameOriginPath(value: unknown, origin: string): string {
    const raw = text(value);
    // `\` reads as `/` in http(s) addresses, so `/\host` would be `//host`
    if (!raw.startsWith('/') || raw.startsWith('//') || raw.includes('\\')) {
        return '/';
    }

    try {
        const url = new URL(raw, origin);
        return url.origin === origin ? `${url.pathname}${url.search}${url.hash}` : '/';
    } catch {
        return '/';
    }
}

/**
 * The settings as the worker can trust them: anything missing or of another type gets a default.
 */
export function readSettings(raw: unknown, origin: string): ServiceWorkerSettings {
    const settings =
        typeof raw === 'object' && raw !== null ? (raw as Record<string, unknown>) : {};
    const host = new URL(origin).host;
    const title = text(settings.fallbackTitle);
    const testTitle = text(settings.testTitle);

    return {
        fallbackTitle: title !== '' ? title : host,
        fallbackBody: text(settings.fallbackBody),
        fallbackPath: sameOriginPath(settings.fallbackPath, origin),
        icon: text(settings.icon),
        testTitle: testTitle !== '' ? testTitle : host,
        testBody: text(settings.testBody),
        testPath: sameOriginPath(settings.testPath, origin),
    };
}
