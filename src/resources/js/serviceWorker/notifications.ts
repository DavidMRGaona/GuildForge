import type { ServiceWorkerSettings } from './settings';

/**
 * What the worker hands to `showNotification()`.
 */
export interface NotificationRequest {
    title: string;
    options: NotificationOptions;
}

/**
 * The notification shown when the notice itself cannot be read: it only says there is one and
 * leads to the list of notices. A push message must always show something.
 */
export function fallbackNotification(settings: ServiceWorkerSettings): NotificationRequest {
    const options: NotificationOptions = {
        body: settings.fallbackBody,
        data: { url: settings.fallbackPath },
    };

    if (settings.icon !== '') {
        options.icon = settings.icon;
    }

    return { title: settings.fallbackTitle, options };
}

/**
 * The absolute address a clicked notification opens: the one it carries when it is a path of this
 * site, the fallback otherwise (another site, another scheme, a backslash, nothing at all).
 */
export function notificationTargetUrl(data: unknown, origin: string, fallbackPath: string): string {
    const fallback = new URL(fallbackPath, origin).href;
    const raw =
        typeof data === 'object' && data !== null
            ? (data as Record<string, unknown>).url
            : undefined;

    // URL parsers read `\` as `/` in http(s) addresses, so `/\host` is `//host`: never a path here
    if (
        typeof raw !== 'string' ||
        !raw.startsWith('/') ||
        raw.startsWith('//') ||
        raw.includes('\\')
    ) {
        return fallback;
    }

    try {
        const url = new URL(raw, origin);
        return url.origin === origin ? url.href : fallback;
    } catch {
        return fallback;
    }
}

function withoutFragment(address: string): string {
    const hash = address.indexOf('#');
    return hash === -1 ? address : address.slice(0, hash);
}

/**
 * A window of the site already showing that address (its fragment aside), to focus it instead of
 * opening another.
 */
export function findWindowAt<T extends { url: string }>(
    windows: readonly T[],
    address: string
): T | undefined {
    const wanted = withoutFragment(address);
    return windows.find((window) => withoutFragment(window.url) === wanted);
}
