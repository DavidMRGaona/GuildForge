import type { ServiceWorkerSettings } from './settings';

/**
 * Options of `showNotification()`. `renotify` (alert again when a notification with the same tag
 * replaces another) is supported by browsers but missing from TypeScript's types.
 */
export type ShownNotificationOptions = NotificationOptions & { renotify?: boolean };

/**
 * What the worker hands to `showNotification()`.
 */
export interface NotificationRequest {
    title: string;
    options: ShownNotificationOptions;
}

/** Ids of the site's notices: UUIDs (core#803). */
const NOTICE_ID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

/** Tags the site gives: `notice-` and the notice's group key or id (up to 191 characters). */
const TAG = /^[A-Za-z0-9._:-]{1,200}$/;

function field(value: unknown, key: string): unknown {
    return typeof value === 'object' && value !== null
        ? (value as Record<string, unknown>)[key]
        : undefined;
}

/**
 * The notice id of a push message, the only thing it carries; null for anything else.
 */
export function noticeIdFrom(payload: unknown): string | null {
    const id = field(payload, 'id');
    return typeof id === 'string' && NOTICE_ID.test(id) ? id : null;
}

/**
 * Whether a tab of the site is visible and focused: the bell already tells the person, so the
 * system notification would only repeat it.
 */
export function hasFocusedWindow(
    windows: readonly { focused: boolean; visibilityState: string }[]
): boolean {
    return windows.some((window) => window.focused && window.visibilityState === 'visible');
}

/**
 * The notification for the notice the site answered (`GET /avisos/{id}/push`), or null when the
 * answer is not one the worker can trust: the generic notification shows then.
 */
export function noticeNotification(
    json: unknown,
    settings: ServiceWorkerSettings,
    origin: string
): NotificationRequest | null {
    const title = field(json, 'title');
    const body = field(json, 'body');
    const url = field(json, 'url');
    const tag = field(json, 'tag');

    if (
        typeof title !== 'string' ||
        title === '' ||
        typeof tag !== 'string' ||
        !TAG.test(tag) ||
        (body !== null && body !== undefined && typeof body !== 'string') ||
        typeof url !== 'string' ||
        sitePathUrl(url, origin) === null
    ) {
        return null;
    }

    const options: ShownNotificationOptions = { tag, renotify: true, data: { url } };

    if (typeof body === 'string' && body !== '') {
        options.body = body;
    }
    const icon = field(json, 'icon');
    if (
        typeof icon === 'string' &&
        (icon.startsWith('https://') || sitePathUrl(icon, origin) !== null)
    ) {
        options.icon = icon;
    } else if (settings.icon !== '') {
        options.icon = settings.icon;
    }

    return { title, options };
}

/**
 * The notification shown when the notice itself cannot be read: it only says there is one and
 * leads to the list of notices. A push message must always show something.
 */
/** Tag of the test notice: a second test replaces the first. */
const TEST_TAG = 'push-test';

/**
 * Whether a push message is the test sent from the «Avisos» tab (core#804): `{"test": true}`,
 * which carries no notice.
 */
export function isTestPush(payload: unknown): boolean {
    return field(payload, 'test') === true;
}

/**
 * The test notice, with the site's own fixed text: nothing of the person travels in it.
 */
export function testNotification(settings: ServiceWorkerSettings): NotificationRequest {
    const options: ShownNotificationOptions = {
        body: settings.testBody,
        tag: TEST_TAG,
        data: { url: settings.testPath },
    };

    if (settings.icon !== '') {
        options.icon = settings.icon;
    }

    return { title: settings.testTitle, options };
}

export function fallbackNotification(settings: ServiceWorkerSettings): NotificationRequest {
    const options: ShownNotificationOptions = {
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
    return sitePathUrl(field(data, 'url'), origin) ?? new URL(fallbackPath, origin).href;
}

/**
 * The absolute address of a path of this site, or null for anything else: another site, another
 * scheme, `//host`, or a backslash (URL parsers read `\` as `/`, so `/\host` is `//host`).
 */
export function sitePathUrl(raw: unknown, origin: string): string | null {
    if (
        typeof raw !== 'string' ||
        !raw.startsWith('/') ||
        raw.startsWith('//') ||
        raw.includes('\\')
    ) {
        return null;
    }

    try {
        const url = new URL(raw, origin);
        return url.origin === origin ? url.href : null;
    } catch {
        return null;
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
