/**
 * Registers the site's service worker (core#802), the ground of browser push notifications.
 * Its URL is fixed (the scope is the whole site); the browser finds a new version by comparing it.
 */
export const SERVICE_WORKER_URL = '/sw.js';

/** What registering needs from the browser, so tests can stand in for it. */
export interface ServiceWorkerEnvironment {
    navigator: { serviceWorker?: Pick<ServiceWorkerContainer, 'register'> };
    isSecureContext: boolean;
}

/**
 * Registers the worker where the browser allows it (service workers need a secure context).
 * A failure is only reported in the console: the page never depends on it.
 */
export async function registerServiceWorker(
    env: ServiceWorkerEnvironment = window
): Promise<ServiceWorkerRegistration | null> {
    const container = env.navigator.serviceWorker;
    if (container === undefined || !env.isSecureContext) {
        return null;
    }

    try {
        // Never from the HTTP cache: a deploy reaches the browser on its next check
        return await container.register(SERVICE_WORKER_URL, { scope: '/', updateViaCache: 'none' });
    } catch (error) {
        console.warn('[serviceWorker] Registration failed', error);
        return null;
    }
}

/**
 * Registers it once the page has loaded, so it never competes with the page's own requests.
 */
export function registerServiceWorkerAfterLoad(): void {
    if (document.readyState === 'complete') {
        void registerServiceWorker();
        return;
    }

    window.addEventListener('load', () => void registerServiceWorker(), { once: true });
}
