/**
 * The site's service worker (core#802), served at `/sw.js` with `self.serviceWorkerSettings` written
 * before it. It handles notifications only: no `fetch` listener and no cache, so it never serves an
 * old page, or one built for another account.
 *
 * Type-checked on its own with the WebWorker library (tsconfig.sw.json); the modules it imports stay
 * free of worker types so the page's tests can import them.
 */
import { fallbackNotification, findWindowAt, notificationTargetUrl } from './notifications';
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

self.addEventListener('push', (event) => {
    const { title, options } = fallbackNotification(settings);
    event.waitUntil(self.registration.showNotification(title, options));
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
