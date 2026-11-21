import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    applicationServerKey,
    forgetThisDevice,
    subscribedWithKey,
    subscribeThisDevice,
    syncThisDevice,
    WEB_PUSH_SUBSCRIPTION_URL,
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
        permission?: NotificationPermission;
        status?: number;
        supported?: boolean;
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

        await expect(subscribeThisDevice(PUBLIC_KEY, env)).resolves.toBe(true);

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

        await expect(subscribeThisDevice(PUBLIC_KEY, env)).resolves.toBe(true);

        expect(methods(fetch)).toEqual(['DELETE', 'POST']);
        expect(old.unsubscribe).toHaveBeenCalled();
    });

    it('fails quietly in a browser without push', async () => {
        const { env, fetch } = environment({ supported: false });

        await expect(subscribeThisDevice(PUBLIC_KEY, env)).resolves.toBe(false);
        expect(fetch).not.toHaveBeenCalled();
    });

    it('drops the browser subscription when the site refuses it', async () => {
        const { env, created } = environment({ status: 422 });

        await expect(subscribeThisDevice(PUBLIC_KEY, env)).resolves.toBe(false);
        expect(created.unsubscribe).toHaveBeenCalled();
    });
});

describe('syncThisDevice', () => {
    it('refreshes the account own subscription once per session, never creating one', async () => {
        const { env, fetch } = environment({ existing: fakeSubscription() });

        await syncThisDevice(PUBLIC_KEY, env);
        await syncThisDevice(PUBLIC_KEY, env);

        expect(fetch).toHaveBeenCalledTimes(1);
        expect(fetch).toHaveBeenCalledWith(
            WEB_PUSH_SUBSCRIPTION_URL,
            expect.objectContaining({
                method: 'PUT',
                body: JSON.stringify({ endpoint: SUBSCRIPTION_JSON.endpoint }),
            })
        );
    });

    it('drops from the browser a subscription the site does not know for this account', async () => {
        // Another account subscribed this browser on a shared computer: it is not taken over
        const someoneElses = fakeSubscription();
        const { env, fetch, storage } = environment({ existing: someoneElses, status: 404 });

        await syncThisDevice(PUBLIC_KEY, env);

        expect(methods(fetch)).toEqual(['PUT']);
        expect(someoneElses.unsubscribe).toHaveBeenCalled();
        expect(storage.has('webPush.synced')).toBe(false);
    });

    it('keeps the browser subscription when the site could not be reached', async () => {
        const subscription = fakeSubscription();
        const { env, storage } = environment({ existing: subscription, status: 503 });

        await syncThisDevice(PUBLIC_KEY, env);

        expect(subscription.unsubscribe).not.toHaveBeenCalled();
        expect(storage.has('webPush.synced')).toBe(false);
    });

    it('never sends a subscription made with other keys: it drops it', async () => {
        const old = fakeSubscription(OLD_KEY);
        const { env, fetch } = environment({ existing: old });

        await syncThisDevice(PUBLIC_KEY, env);

        expect(methods(fetch)).toEqual(['DELETE']);
        expect(old.unsubscribe).toHaveBeenCalled();
    });

    it('does nothing without a subscription or without permission', async () => {
        const without = environment({ existing: null });
        const denied = environment({ existing: fakeSubscription(), permission: 'denied' });

        await syncThisDevice(PUBLIC_KEY, without.env);
        await syncThisDevice(PUBLIC_KEY, denied.env);

        expect(without.fetch).not.toHaveBeenCalled();
        expect(denied.fetch).not.toHaveBeenCalled();
    });
});

describe('forgetThisDevice', () => {
    it('asks the site to forget this browser and drops its subscription', async () => {
        const subscription = fakeSubscription();
        const { env, fetch, storage } = environment({ existing: subscription, status: 204 });
        storage.set('webPush.synced', '1');

        await forgetThisDevice(env);

        expect(fetch).toHaveBeenCalledWith(
            WEB_PUSH_SUBSCRIPTION_URL,
            expect.objectContaining({
                method: 'DELETE',
                body: JSON.stringify({ endpoint: SUBSCRIPTION_JSON.endpoint }),
            })
        );
        expect(subscription.unsubscribe).toHaveBeenCalled();
        expect(storage.has('webPush.synced')).toBe(false);
    });

    it('never throws, so signing out always goes on', async () => {
        const { env } = environment({ existing: fakeSubscription() });
        (env.fetch as unknown as ReturnType<typeof vi.fn>).mockRejectedValue(new Error('offline'));

        await expect(forgetThisDevice(env)).resolves.toBeUndefined();
    });
});
