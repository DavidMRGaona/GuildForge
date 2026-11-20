import { beforeEach, describe, expect, it, vi } from 'vitest';
import {
    applicationServerKey,
    forgetThisDevice,
    subscribeThisDevice,
    syncThisDevice,
    WEB_PUSH_SUBSCRIPTION_URL,
    type WebPushEnvironment,
} from '../webPush';

const PUBLIC_KEY =
    'BIiq-ImwLDB8OcUrATp0p_oGpRJ2nrH7cU-S0-GOTpwHm-7cUORDXVuq2Ct_MD2IrHe-FNzW1KAvn7un17hY47s';
const SUBSCRIPTION_JSON = {
    endpoint: 'https://fcm.googleapis.com/fcm/send/device',
    keys: { p256dh: 'key', auth: 'secret' },
};

interface FakeSubscription {
    toJSON: () => typeof SUBSCRIPTION_JSON;
    unsubscribe: ReturnType<typeof vi.fn>;
}

function environment(
    options: {
        existing?: boolean;
        permission?: NotificationPermission;
        fetchStatus?: number;
        supported?: boolean;
    } = {}
): {
    env: WebPushEnvironment;
    fetch: ReturnType<typeof vi.fn>;
    subscribe: ReturnType<typeof vi.fn>;
    subscription: FakeSubscription;
    storage: Map<string, string>;
} {
    const subscription: FakeSubscription = {
        toJSON: () => SUBSCRIPTION_JSON,
        unsubscribe: vi.fn().mockResolvedValue(true),
    };
    const subscribe = vi.fn().mockResolvedValue(subscription);
    const fetch = vi.fn().mockResolvedValue({ ok: (options.fetchStatus ?? 201) < 400 });
    const storage = new Map<string, string>();
    const pushManager = {
        getSubscription: vi.fn().mockResolvedValue(options.existing ? subscription : null),
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
        subscription,
        storage,
    };
}

describe('applicationServerKey', () => {
    it('decodes the base64url public key into the 65 bytes of a P-256 point', () => {
        const bytes = applicationServerKey(PUBLIC_KEY);

        expect(bytes).toHaveLength(65);
        expect(bytes[0]).toBe(4);
    });
});

describe('subscribeThisDevice', () => {
    beforeEach(() => {
        document.cookie = 'XSRF-TOKEN=token-value';
    });

    it('subscribes with the site key and hands the subscription to the site', async () => {
        const { env, fetch, subscribe } = environment();

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

    it('fails quietly in a browser without push', async () => {
        const { env, fetch } = environment({ supported: false });

        await expect(subscribeThisDevice(PUBLIC_KEY, env)).resolves.toBe(false);
        expect(fetch).not.toHaveBeenCalled();
    });

    it('drops the browser subscription when the site refuses it', async () => {
        const { env, subscription } = environment({ fetchStatus: 422 });

        await expect(subscribeThisDevice(PUBLIC_KEY, env)).resolves.toBe(false);
        expect(subscription.unsubscribe).toHaveBeenCalled();
    });
});

describe('syncThisDevice', () => {
    it('tells the site about this browser once per session', async () => {
        const { env, fetch } = environment({ existing: true });

        await syncThisDevice(env);
        await syncThisDevice(env);

        expect(fetch).toHaveBeenCalledTimes(1);
    });

    it('does nothing without a subscription or without permission', async () => {
        const without = environment({ existing: false });
        const denied = environment({ existing: true, permission: 'denied' });

        await syncThisDevice(without.env);
        await syncThisDevice(denied.env);

        expect(without.fetch).not.toHaveBeenCalled();
        expect(denied.fetch).not.toHaveBeenCalled();
    });
});

describe('forgetThisDevice', () => {
    it('asks the site to forget this browser and drops its subscription', async () => {
        const { env, fetch, subscription, storage } = environment({ existing: true });
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
        const { env } = environment({ existing: true });
        (env.fetch as unknown as ReturnType<typeof vi.fn>).mockRejectedValue(new Error('offline'));

        await expect(forgetThisDevice(env)).resolves.toBeUndefined();
    });
});
