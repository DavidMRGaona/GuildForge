/**
 * This browser and the account's push notifications (core#803): subscribe it, keep the site told
 * about it, and forget it when signing out. Only ever started by the person (a button, core#804),
 * never when a page loads; and nothing here throws, so no page depends on it.
 */
import { csrfHeaders } from '@/utils/csrf';

export const WEB_PUSH_SUBSCRIPTION_URL = '/avisos/push/suscripcion';

/** One device of the account, removed from the «Avisos» tab (core#804). */
export const WEB_PUSH_DEVICE_URL = '/avisos/push/dispositivos/';

/** A test notice to this browser (core#804). */
export const WEB_PUSH_TEST_URL = '/avisos/push/prueba';

/**
 * Set, per account, once the site was told about this browser in this session (sessionStorage):
 * another account signing in on the same tab is never taken for the one already told.
 */
const SYNCED_PREFIX = 'webPush.synced:';

function syncedKey(userId: string): string {
    return SYNCED_PREFIX + userId;
}

/** What these functions need from the browser, so tests can stand in for it. */
export interface WebPushEnvironment {
    serviceWorker: { ready: Promise<ServiceWorkerRegistration> } | undefined;
    permission: () => NotificationPermission | 'unsupported';
    fetch: typeof globalThis.fetch;
    session: Pick<Storage, 'getItem' | 'setItem' | 'removeItem'> | null;
    /** An iPhone or iPad where the site is not on the home screen: Safari offers push only there. */
    needsHomeScreen?: () => boolean;
    /** How long to wait for the service worker before giving up (it may never register). */
    readyTimeoutMs?: number;
}

/** How long `serviceWorker.ready` may take: it never settles when no worker is registered. */
const READY_TIMEOUT_MS = 5000;

/**
 * Whether this is an iPhone or iPad (iPadOS says it is a Mac, with a touch screen) where the site
 * runs in the browser rather than from the home screen.
 */
export function isAppleMobileOutsideHomeScreen(
    userAgent: string,
    maxTouchPoints: number,
    standalone: boolean
): boolean {
    const appleMobile =
        /iPhone|iPad|iPod/.test(userAgent) || (/Macintosh/.test(userAgent) && maxTouchPoints > 1);

    return appleMobile && !standalone;
}

function browserEnvironment(): WebPushEnvironment {
    const supported =
        typeof navigator !== 'undefined' &&
        'serviceWorker' in navigator &&
        typeof window !== 'undefined' &&
        'PushManager' in window &&
        window.isSecureContext;

    let session: Storage | null = null;
    try {
        session = window.sessionStorage;
    } catch {
        // Storage blocked: every page load tells the site again, which is harmless
    }

    return {
        serviceWorker: supported ? navigator.serviceWorker : undefined,
        permission: () => ('Notification' in window ? Notification.permission : 'unsupported'),
        fetch: (input, init) => window.fetch(input, init),
        session,
        needsHomeScreen: () =>
            typeof navigator !== 'undefined' &&
            typeof window !== 'undefined' &&
            isAppleMobileOutsideHomeScreen(
                navigator.userAgent,
                navigator.maxTouchPoints,
                window.matchMedia?.('(display-mode: standalone)').matches === true ||
                    (navigator as Navigator & { standalone?: boolean }).standalone === true
            ),
    };
}

/**
 * Where this browser stands with push (core#804), for the «Avisos» tab:
 * - `unsupported`: it cannot receive push notifications;
 * - `home-screen`: an iPhone or iPad that receives them once the site is on the home screen;
 * - `denied`: the person blocked notifications for the site, only the browser can undo it;
 * - `available`: it can, asking for permission when the person clicks;
 * - `subscribed`: it already receives the account's notifications.
 */
export type ThisDevicePushState =
    'unsupported' | 'home-screen' | 'denied' | 'available' | 'subscribed';

export async function thisDevicePushState(
    env: WebPushEnvironment = browserEnvironment()
): Promise<ThisDevicePushState> {
    if (env.needsHomeScreen?.() === true) {
        return 'home-screen';
    }

    const permission = env.permission();
    if (env.serviceWorker === undefined || permission === 'unsupported') {
        return 'unsupported';
    }

    if (permission === 'denied') {
        return 'denied';
    }

    if (permission === 'granted' && (await currentSubscription(await pushManager(env))) !== null) {
        return 'subscribed';
    }

    return 'available';
}

/**
 * The VAPID public key (base64url) as the bytes `pushManager.subscribe()` takes.
 */
export function applicationServerKey(publicKey: string): Uint8Array<ArrayBuffer> {
    const base64 = publicKey.replace(/-/g, '+').replace(/_/g, '/');
    const padded = base64 + '='.repeat((4 - (base64.length % 4)) % 4);
    const binary = atob(padded);
    const bytes = new Uint8Array(new ArrayBuffer(binary.length));
    for (let i = 0; i < binary.length; i++) {
        bytes[i] = binary.charCodeAt(i);
    }

    return bytes;
}

async function pushManager(env: WebPushEnvironment): Promise<PushManager | null> {
    if (env.serviceWorker === undefined) {
        return null;
    }

    let timer: ReturnType<typeof setTimeout> | undefined;
    const timeout = new Promise<null>((resolve) => {
        timer = setTimeout(() => resolve(null), env.readyTimeoutMs ?? READY_TIMEOUT_MS);
    });

    try {
        const registration = await Promise.race([env.serviceWorker.ready, timeout]);
        return registration?.pushManager ?? null;
    } catch {
        return null;
    } finally {
        clearTimeout(timer);
    }
}

/**
 * Sends a request about this browser's subscription; the status, or null when it never arrived.
 */
async function send(
    env: WebPushEnvironment,
    method: 'POST' | 'PUT' | 'DELETE',
    body: unknown
): Promise<number | null> {
    try {
        const response = await env.fetch(WEB_PUSH_SUBSCRIPTION_URL, {
            method,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...csrfHeaders(),
            },
            body: JSON.stringify(body),
        });

        return response.status;
    } catch {
        return null;
    }
}

/**
 * A request about a device of the account, without a body; the status, or null when it never arrived.
 */
async function request(
    env: WebPushEnvironment,
    method: 'POST' | 'DELETE',
    url: string
): Promise<number | null> {
    try {
        const response = await env.fetch(url, {
            method,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', ...csrfHeaders() },
        });

        return response.status;
    } catch {
        return null;
    }
}

function isSuccess(status: number | null): boolean {
    return status !== null && status >= 200 && status < 300;
}

function remember(env: WebPushEnvironment, userId: string): void {
    try {
        env.session?.setItem(syncedKey(userId), '1');
    } catch {
        // Not remembered: the next page tells the site again
    }
}

function forgetRemembered(env: WebPushEnvironment, userId: string): void {
    try {
        env.session?.removeItem(syncedKey(userId));
    } catch {
        // Nothing to forget
    }
}

async function currentSubscription(manager: PushManager | null): Promise<PushSubscription | null> {
    try {
        return (await manager?.getSubscription()) ?? null;
    } catch {
        return null;
    }
}

/**
 * Whether the browser's subscription was made with this VAPID key. Unknown (the browser does not
 * say) counts as the same: only a key that is known to differ is replaced.
 */
export function subscribedWithKey(subscription: PushSubscription, publicKey: string): boolean {
    const key = subscription.options?.applicationServerKey ?? null;
    if (key === null) {
        return true;
    }

    const current = applicationServerKey(publicKey);
    const stored = new Uint8Array(key);

    return (
        stored.length === current.length && stored.every((byte, index) => byte === current[index])
    );
}

/**
 * Drops a subscription from the site (when it is this account's) and from the browser.
 */
async function drop(
    env: WebPushEnvironment,
    userId: string,
    subscription: PushSubscription
): Promise<void> {
    forgetRemembered(env, userId);

    const endpoint = subscription.toJSON().endpoint;
    if (endpoint !== undefined) {
        await send(env, 'DELETE', { endpoint });
    }
    await subscription.unsubscribe().catch(() => false);
}

/**
 * Subscribes this browser and tells the site. Asks the browser's permission, so only call it from
 * a click: it is the only way a browser starts receiving an account's notifications, or moves to
 * another account. False when the browser cannot, the person refused or the site did not take it.
 */
export async function subscribeThisDevice(
    publicKey: string,
    userId: string,
    env: WebPushEnvironment = browserEnvironment()
): Promise<boolean> {
    const manager = await pushManager(env);
    if (manager === null) {
        return false;
    }

    // Made with keys the site no longer has: the browser would refuse the new one
    const existing = await currentSubscription(manager);
    if (existing !== null && !subscribedWithKey(existing, publicKey)) {
        await drop(env, userId, existing);
    }

    let subscription: PushSubscription;
    try {
        subscription = await manager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: applicationServerKey(publicKey),
        });
    } catch {
        return false;
    }

    if (isSuccess(await send(env, 'POST', subscription.toJSON()))) {
        remember(env, userId);
        return true;
    }

    // The site did not take it: a subscription it cannot send to would only confuse the browser
    await subscription.unsubscribe().catch(() => false);
    return false;
}

/**
 * Tells the site, once per session, about the subscription this browser already has: it counts as
 * a use (unused ones go after 90 days) and lets signing out forget it on the server too. It only
 * refreshes the account's own: when the site does not know it for this account (another account
 * subscribed this browser, or it was forgotten), it is dropped from the browser. Never asks for
 * permission and never subscribes.
 */
export async function syncThisDevice(
    publicKey: string,
    userId: string,
    env: WebPushEnvironment = browserEnvironment()
): Promise<void> {
    if (env.permission() !== 'granted' || env.session?.getItem(syncedKey(userId)) === '1') {
        return;
    }

    const subscription = await currentSubscription(await pushManager(env));
    if (subscription === null) {
        return;
    }

    if (!subscribedWithKey(subscription, publicKey)) {
        await drop(env, userId, subscription);
        return;
    }

    const status = await send(env, 'PUT', { endpoint: subscription.toJSON().endpoint });
    if (isSuccess(status)) {
        remember(env, userId);
    } else if (status === 404) {
        forgetRemembered(env, userId);
        await subscription.unsubscribe().catch(() => false);
    }
}

/**
 * Tells the site about this browser's subscription now, even when this browser tab already did in
 * this session (core#804): the mark outlives a sign-out from the panel or an expired session, while
 * the site's own record of the session's browser does not. The device is then marked as this one
 * again, or dropped from the browser when the site no longer knows it for this account.
 */
export async function resyncThisDevice(
    publicKey: string,
    userId: string,
    env: WebPushEnvironment = browserEnvironment()
): Promise<void> {
    forgetRemembered(env, userId);
    await syncThisDevice(publicKey, userId, env);
}

/**
 * Removes one device of the account (core#804); for this browser, it also drops the browser's
 * own subscription. Whether the site removed it.
 */
export async function removeDevice(
    deviceId: string,
    userId: string,
    isThisDevice: boolean,
    env: WebPushEnvironment = browserEnvironment()
): Promise<boolean> {
    const status = await request(env, 'DELETE', WEB_PUSH_DEVICE_URL + encodeURIComponent(deviceId));

    if (isThisDevice && (isSuccess(status) || status === 404)) {
        forgetRemembered(env, userId);
        const subscription = await currentSubscription(await pushManager(env));
        await subscription?.unsubscribe().catch(() => false);
    }

    return isSuccess(status);
}

/**
 * The outcome of a test notice (core#804): sent to the push service, this browser no longer
 * receives notices (the site forgot it), or it could not be sent now.
 */
export type TestNoticeOutcome = 'sent' | 'gone' | 'unavailable';

/**
 * Sends a test notice to this browser. When the push service dropped it, the browser drops its
 * subscription too, so a click on «Recibir avisos» starts afresh.
 */
export async function sendTestNotice(
    userId: string,
    env: WebPushEnvironment = browserEnvironment()
): Promise<TestNoticeOutcome> {
    const status = await request(env, 'POST', WEB_PUSH_TEST_URL);

    if (isSuccess(status)) {
        return 'sent';
    }

    if (status === 410 || status === 404) {
        forgetRemembered(env, userId);
        const subscription = await currentSubscription(await pushManager(env));
        await subscription?.unsubscribe().catch(() => false);

        return 'gone';
    }

    return 'unavailable';
}

/**
 * Stops push in this browser before signing out: the site forgets it, then the browser drops it.
 * Never throws; the server forgets it on sign-out anyway.
 */
export async function forgetThisDevice(
    userId: string,
    env: WebPushEnvironment = browserEnvironment()
): Promise<void> {
    forgetRemembered(env, userId);

    const subscription = await currentSubscription(await pushManager(env));
    if (subscription !== null) {
        await drop(env, userId, subscription);
    }
}
