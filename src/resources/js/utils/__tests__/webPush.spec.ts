import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    applicationServerKey,
    forgetThisDevice,
    isAppleMobileOutsideHomeScreen,
    removeDevice,
    resyncThisDevice,
    sendTestNotice,
    subscribedWithKey,
    subscribeThisDevice,
    syncThisDevice,
    thisDevicePushState,
    WEB_PUSH_DEVICE_URL,
    WEB_PUSH_SUBSCRIPTION_URL,
    WEB_PUSH_TEST_URL,
    type WebPushEnvironment,
} from '../webPush';

const PUBLIC_KEY =
    'BIiq-ImwLDB8OcUrATp0p_oGpRJ2nrH7cU-S0-GOTpwHm-7cUORDXVuq2Ct_MD2IrHe-FNzW1KAvn7un17hY47s';
const OLD_KEY =
    'BCRdgiAY4WYUzixZ3XtywS7UdIG6IcXQ_vA-JgDXJo2U8V6BoH_aZXv2OVNVKIIQWpGA2fjDIp0yDLtzv8Lo1xY';
const SUBSCRIPTION_JSON = {
    endpoint: 'https://fcm.googleapis.com/fcm/send/device',
    keys: { p256dh: 'key', auth: 'secret' },
};

interface FakeSubscription {
    options: { applicationServerKey: ArrayBuffer | null };
    toJSON: () => typeof SUBSCRIPTION_JSON;
    unsubscribe: ReturnType<typeof vi.fn>;
}

function fakeSubscription(key: string | null = PUBLIC_KEY): FakeSubscription {
    return {
        options: { applicationServerKey: key === null ? null : applicationServerKey(key).buffer },
        toJSON: () => SUBSCRIPTION_JSON,
        unsubscribe: vi.fn().mockResolvedValue(true),
    };
}

function environment(
    options: {
        existing?: FakeSubscription | null;
        permission?: NotificationPermission | 'unsupported';
        status?: number;
        supported?: boolean;
        homeScreen?: boolean;
    } = {}
): {
    env: WebPushEnvironment;
    fetch: ReturnType<typeof vi.fn>;
    subscribe: ReturnType<typeof vi.fn>;
    created: FakeSubscription;
    storage: Map<string, string>;
} {
    const created = fakeSubscription();
    const subscribe = vi.fn().mockResolvedValue(created);
    const status = options.status ?? 200;
    const fetch = vi.fn().mockResolvedValue({ ok: status < 400, status });
    const storage = new Map<string, string>();
    const pushManager = {
        getSubscription: vi.fn().mockResolvedValue(options.existing ?? null),
        subscribe,
    };

    return {
        env: {
            serviceWorker:
                options.supported === false
                    ? undefined
                    : {
                          ready: Promise.resolve({
                              pushManager,
                          } as unknown as ServiceWorkerRegistration),
                      },
            permission: () => options.permission ?? 'granted',
            fetch: fetch as unknown as typeof globalThis.fetch,
            session: {
                getItem: (key: string) => storage.get(key) ?? null,
                setItem: (key: string, value: string) => void storage.set(key, value),
                removeItem: (key: string) => void storage.delete(key),
            },
            needsHomeScreen: () => options.homeScreen === true,
        },
        fetch,
        subscribe,
        created,
        storage,
    };
}

function methods(fetch: ReturnType<typeof vi.fn>): string[] {
    return fetch.mock.calls.map((call) => (call[1] as RequestInit).method ?? '');
}

describe('applicationServerKey', () => {
    it('decodes the base64url public key into the 65 bytes of a P-256 point', () => {
        const bytes = applicationServerKey(PUBLIC_KEY);

        expect(bytes).toHaveLength(65);
        expect(bytes[0]).toBe(4);
    });
});

describe('subscribedWithKey', () => {
    it('tells a subscription made with the current key from one made with another', () => {
        expect(
            subscribedWithKey(fakeSubscription() as unknown as PushSubscription, PUBLIC_KEY)
        ).toBe(true);
        expect(
            subscribedWithKey(fakeSubscription(OLD_KEY) as unknown as PushSubscription, PUBLIC_KEY)
        ).toBe(false);
        expect(
            subscribedWithKey(fakeSubscription(null) as unknown as PushSubscription, PUBLIC_KEY)
        ).toBe(true);
    });
});

describe('subscribeThisDevice', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=token-value';
    });

    it('subscribes with the site key and hands the subscription to the site', async () => {
        const { env, fetch, subscribe } = environment({ status: 201 });

        await expect(subscribeThisDevice(PUBLIC_KEY, 'user-a', env)).resolves.toBe(true);

        expect(subscribe).toHaveBeenCalledWith({
            userVisibleOnly: true,
            applicationServerKey: applicationServerKey(PUBLIC_KEY),
        });
        expect(fetch).toHaveBeenCalledWith(
            WEB_PUSH_SUBSCRIPTION_URL,
            expect.objectContaining({
                method: 'POST',
                credentials: 'same-origin',
                body: JSON.stringify(SUBSCRIPTION_JSON),
                headers: expect.objectContaining({ 'X-XSRF-TOKEN': 'token-value' }),
            })
        );
    });

    it('first drops a subscription made with keys the site no longer has', async () => {
        const old = fakeSubscription(OLD_KEY);
        const { env, fetch } = environment({ existing: old, status: 201 });

        await expect(subscribeThisDevice(PUBLIC_KEY, 'user-a', env)).resolves.toBe(true);

        expect(methods(fetch)).toEqual(['DELETE', 'POST']);
        expect(old.unsubscribe).toHaveBeenCalled();
    });

    it('fails quietly in a browser without push', async () => {
        const { env, fetch } = environment({ supported: false });

        await expect(subscribeThisDevice(PUBLIC_KEY, 'user-a', env)).resolves.toBe(false);
        expect(fetch).not.toHaveBeenCalled();
    });

    it('drops the browser subscription when the site refuses it', async () => {
        const { env, created } = environment({ status: 422 });

        await expect(subscribeThisDevice(PUBLIC_KEY, 'user-a', env)).resolves.toBe(false);
        expect(created.unsubscribe).toHaveBeenCalled();
    });
});

describe('syncThisDevice', () => {
    it('refreshes the account own subscription once per session, never creating one', async () => {
        const { env, fetch } = environment({ existing: fakeSubscription() });

        await syncThisDevice(PUBLIC_KEY, 'user-a', env);
        await syncThisDevice(PUBLIC_KEY, 'user-a', env);

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(fetch).toHaveBeenCalledWith(
            WEB_PUSH_SUBSCRIPTION_URL,
            expect.objectContaining({
                method: 'PUT',
                body: JSON.stringify({ endpoint: SUBSCRIPTION_JSON.endpoint }),
            })
        );
    });

    it('checks again when another account signs in on the same tab', async () => {
        // The first account's session ended; the next one must not be taken for it
        const subscription = fakeSubscription();
        const { env, fetch, storage } = environment({ existing: subscription });
        await syncThisDevice(PUBLIC_KEY, 'user-a', env);
        fetch.mockResolvedValue({ ok: false, status: 404 });

        await syncThisDevice(PUBLIC_KEY, 'user-b', env);

        expect(methods(fetch)).toEqual(['PUT', 'PUT']);
        expect(subscription.unsubscribe).toHaveBeenCalled();
        expect(storage.has('webPush.synced:user-b')).toBe(false);
    });

    it('drops from the browser a subscription the site does not know for this account', async () => {
        // Another account subscribed this browser on a shared computer: it is not taken over
        const someoneElses = fakeSubscription();
        const { env, fetch, storage } = environment({ existing: someoneElses, status: 404 });

        await syncThisDevice(PUBLIC_KEY, 'user-a', env);

        expect(methods(fetch)).toEqual(['PUT']);
        expect(someoneElses.unsubscribe).toHaveBeenCalled();
        expect(storage.has('webPush.synced:user-a')).toBe(false);
    });

    it('keeps the browser subscription when the site could not be reached', async () => {
        const subscription = fakeSubscription();
        const { env, storage } = environment({ existing: subscription, status: 503 });

        await syncThisDevice(PUBLIC_KEY, 'user-a', env);

        expect(subscription.unsubscribe).not.toHaveBeenCalled();
        expect(storage.has('webPush.synced:user-a')).toBe(false);
    });

    it('never sends a subscription made with other keys: it drops it', async () => {
        const old = fakeSubscription(OLD_KEY);
        const { env, fetch } = environment({ existing: old });

        await syncThisDevice(PUBLIC_KEY, 'user-a', env);

        expect(methods(fetch)).toEqual(['DELETE']);
        expect(old.unsubscribe).toHaveBeenCalled();
    });

    it('does nothing without a subscription or without permission', async () => {
        const without = environment({ existing: null });
        const denied = environment({ existing: fakeSubscription(), permission: 'denied' });

        await syncThisDevice(PUBLIC_KEY, 'user-a', without.env);
        await syncThisDevice(PUBLIC_KEY, 'user-a', denied.env);

        expect(without.fetch).not.toHaveBeenCalled();
        expect(denied.fetch).not.toHaveBeenCalled();
    });
});

describe('forgetThisDevice', () => {
    it('asks the site to forget this browser and drops its subscription', async () => {
        const subscription = fakeSubscription();
        const { env, fetch, storage } = environment({ existing: subscription, status: 204 });
        storage.set('webPush.synced:user-a', '1');

        await forgetThisDevice('user-a', env);

        expect(fetch).toHaveBeenCalledWith(
            WEB_PUSH_SUBSCRIPTION_URL,
            expect.objectContaining({
                method: 'DELETE',
                body: JSON.stringify({ endpoint: SUBSCRIPTION_JSON.endpoint }),
            })
        );
        expect(subscription.unsubscribe).toHaveBeenCalled();
        expect(storage.has('webPush.synced:user-a')).toBe(false);
    });

    it('never throws, so signing out always goes on', async () => {
        const { env } = environment({ existing: fakeSubscription() });
        (env.fetch as unknown as ReturnType<typeof vi.fn>).mockRejectedValue(new Error('offline'));

        await expect(forgetThisDevice('user-a', env)).resolves.toBeUndefined();
    });
});

describe('isAppleMobileOutsideHomeScreen', () => {
    const IPHONE =
        'Mozilla/5.0 (iPhone; CPU iPhone OS 18_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Mobile/15E148 Safari/604.1';
    const MAC =
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.1 Safari/605.1.15';

    it('asks an iPhone, or an iPad that says it is a Mac, to add the site to the home screen', () => {
        expect(isAppleMobileOutsideHomeScreen(IPHONE, 5, false)).toBe(true);
        expect(isAppleMobileOutsideHomeScreen(MAC, 5, false)).toBe(true);
    });

    it('leaves alone the site on the home screen and a real Mac', () => {
        expect(isAppleMobileOutsideHomeScreen(IPHONE, 5, true)).toBe(false);
        expect(isAppleMobileOutsideHomeScreen(MAC, 0, false)).toBe(false);
    });
});

describe('thisDevicePushState', () => {
    it('tells each state apart', async () => {
        expect(await thisDevicePushState(environment({ homeScreen: true }).env)).toBe(
            'home-screen'
        );
        expect(await thisDevicePushState(environment({ supported: false }).env)).toBe(
            'unsupported'
        );
        expect(await thisDevicePushState(environment({ permission: 'unsupported' }).env)).toBe(
            'unsupported'
        );
        expect(await thisDevicePushState(environment({ permission: 'denied' }).env)).toBe('denied');
        expect(await thisDevicePushState(environment({ permission: 'default' }).env)).toBe(
            'available'
        );
        expect(await thisDevicePushState(environment({ existing: null }).env)).toBe('available');
        expect(await thisDevicePushState(environment({ existing: fakeSubscription() }).env)).toBe(
            'subscribed'
        );
    });
});

describe('removeDevice', () => {
    it('removes another device of the account and leaves this browser alone', async () => {
        const subscription = fakeSubscription();
        const { env, fetch } = environment({ existing: subscription, status: 204 });

        expect(await removeDevice('device-1', 'user-a', false, env)).toBe(true);

        expect(fetch).toHaveBeenCalledWith(
            WEB_PUSH_DEVICE_URL + 'device-1',
            expect.objectContaining({ method: 'DELETE' })
        );
        expect(subscription.unsubscribe).not.toHaveBeenCalled();
    });

    it('drops the browser subscription too when it removes this device', async () => {
        const subscription = fakeSubscription();
        const { env, storage } = environment({ existing: subscription, status: 204 });
        storage.set('webPush.synced:user-a', '1');

        expect(await removeDevice('device-1', 'user-a', true, env)).toBe(true);

        expect(subscription.unsubscribe).toHaveBeenCalled();
        expect(storage.has('webPush.synced:user-a')).toBe(false);
    });

    it('keeps the browser subscription when the site could not remove it', async () => {
        const subscription = fakeSubscription();
        const { env } = environment({ existing: subscription, status: 500 });

        expect(await removeDevice('device-1', 'user-a', true, env)).toBe(false);
        expect(subscription.unsubscribe).not.toHaveBeenCalled();
    });
});

describe('sendTestNotice', () => {
    it('says the test was sent', async () => {
        const { env, fetch } = environment({ existing: fakeSubscription(), status: 204 });

        expect(await sendTestNotice('user-a', env)).toBe('sent');
        expect(fetch).toHaveBeenCalledWith(
            WEB_PUSH_TEST_URL,
            expect.objectContaining({ method: 'POST' })
        );
    });

    it('drops the subscription the push service no longer takes', async () => {
        const subscription = fakeSubscription();
        const { env } = environment({ existing: subscription, status: 410 });

        expect(await sendTestNotice('user-a', env)).toBe('gone');
        expect(subscription.unsubscribe).toHaveBeenCalled();
    });

    it('keeps everything when it could not be sent now', async () => {
        const subscription = fakeSubscription();
        const { env } = environment({ existing: subscription, status: 503 });

        expect(await sendTestNotice('user-a', env)).toBe('unavailable');
        expect(subscription.unsubscribe).not.toHaveBeenCalled();
    });
});

describe('resyncThisDevice', () => {
    it('tells the site again even when this tab already did in this session', async () => {
        const { env, fetch, storage } = environment({ existing: fakeSubscription(), status: 200 });
        storage.set('webPush.synced:user-a', '1');

        await resyncThisDevice(PUBLIC_KEY, 'user-a', env);

        expect(methods(fetch)).toEqual(['PUT']);
        expect(storage.get('webPush.synced:user-a')).toBe('1');
    });

    it('drops the subscription the site no longer knows for this account', async () => {
        const subscription = fakeSubscription();
        const { env, storage } = environment({ existing: subscription, status: 404 });
        storage.set('webPush.synced:user-a', '1');

        await resyncThisDevice(PUBLIC_KEY, 'user-a', env);

        expect(subscription.unsubscribe).toHaveBeenCalled();
        expect(storage.has('webPush.synced:user-a')).toBe(false);
    });
});

describe('a service worker that never gets ready', () => {
    it('gives up after a while instead of waiting forever', async () => {
        vi.useFakeTimers();
        try {
            const { env } = environment();
            env.serviceWorker = { ready: new Promise<ServiceWorkerRegistration>(() => undefined) };
            env.readyTimeoutMs = 1000;

            const state = thisDevicePushState(env);
            await vi.advanceTimersByTimeAsync(1000);

            await expect(state).resolves.toBe('available');
        } finally {
            vi.useRealTimers();
        }
    });
});
