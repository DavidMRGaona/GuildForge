/**
 * The site's service worker (core#802), served at `/sw.js` with `self.serviceWorkerSettings` written
 * before it. It handles notifications only: no `fetch` listener and no cache, so it never serves an
 * old page, or one built for another account. A push message carries a notice id only (core#803):
 * the notice is read with the person's session, and clicking it opens `/avisos/{id}`, which marks
 * it read and goes where it leads, as the bell does.
 *
 * Type-checked on its own with the WebWorker library (tsconfig.sw.json); the modules it imports stay
 * free of worker types so the page's tests can import them.
 */
import {
    fallbackNotification,
    findWindowAt,
    hasFocusedWindow,
    isTestPush,
    noticeIdFrom,
    noticeNotification,
    notificationTargetUrl,
    testNotification,
    type NotificationRequest,
} from './notifications';
import { readSettings } from './settings';

declare const self: ServiceWorkerGlobalScope & { serviceWorkerSettings?: unknown };

const settings = readSettings(self.serviceWorkerSettings, self.location.origin);

// A new version takes over at once: it holds no state an open page relies on
self.addEventListener('install', () => {
    void self.skipWaiting();
});

self.addEventListener('activate', (event) => {
    event.waitUntil(self.clients.claim());
});

function payloadOf(data: PushMessageData | null): unknown {
    try {
        return data?.json() ?? null;
    } catch {
        return null;
    }
}

/**
 * The notice as the site shows it to this browser's session, which checks it again (core#803).
 * Without a session, or on any error, the generic notification instead.
 */
async function noticeRequest(id: string | null): Promise<NotificationRequest> {
    if (id === null) {
        return fallbackNotification(settings);
    }

    try {
        const response = await fetch(`/avisos/${id}/push`, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { Accept: 'application/json' },
        });

        if (response.ok) {
            const request = noticeNotification(
                await response.json(),
                settings,
                self.location.origin
            );
            if (request !== null) {
                return request;
            }
        }
    } catch {
        // Offline or the site is down: the generic notification still says there is a notice
    }

    return fallbackNotification(settings);
}

async function showPush(data: PushMessageData | null): Promise<void> {
    const payload = payloadOf(data);

    // The test of the «Avisos» tab (core#804) shows even in the tab that asked for it
    if (isTestPush(payload)) {
        const { title, options } = testNotification(settings);
        await self.registration.showNotification(title, options);
        return;
    }

    // A visible, focused tab of the site already shows the notice in its bell
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    if (hasFocusedWindow(windows)) {
        return;
    }

    const { title, options } = await noticeRequest(noticeIdFrom(payload));
    await self.registration.showNotification(title, options);
}

self.addEventListener('push', (event) => {
    event.waitUntil(showPush(event.data));
});

async function openOrFocus(address: string): Promise<void> {
    const windows = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const open = findWindowAt(windows, address);

    if (open !== undefined) {
        await open.focus();
        return;
    }

    await self.clients.openWindow(address);
}

self.addEventListener('notificationclick', (event) => {
    event.notification.close();
    const address = notificationTargetUrl(
        event.notification.data,
        self.location.origin,
        settings.fallbackPath
    );
    event.waitUntil(openOrFocus(address));
});
