import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { effectScope, nextTick, reactive, ref } from 'vue';
import type { EffectScope } from 'vue';
import { flushPromises } from '@vue/test-utils';
import { useRealtimeChannel } from '../useRealtimeChannel';
import type { UseRealtimeChannelReturn } from '../useRealtimeChannel';
import { resetRealtimeConnection, setRealtimeClientFactory } from '@/utils/realtime/connection';
import type {
    RawListener,
    RealtimeChannelHandle,
    RealtimeClient,
    RealtimeConnectionState,
} from '@/utils/realtime/client';
import type { RealtimePayload, RealtimeSharedProps } from '@/types/realtime';

const page = reactive<{ props: Record<string, unknown> }>({ props: {} });

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): typeof page => page,
}));

const CONFIG: RealtimeSharedProps = {
    key: 'example-key',
    host: 'example.test',
    port: 443,
    tls: true,
    authEndpoint: '/broadcasting/auth',
};

class FakeChannel implements RealtimeChannelHandle {
    private readonly granted: Array<() => void> = [];
    private readonly refused: Array<() => void> = [];
    readonly events = new Map<string, Set<RawListener>>();
    readonly whisperListeners = new Map<string, Set<RawListener>>();
    readonly sent: Array<[string, Record<string, unknown>]> = [];

    onSubscribed(callback: () => void): void {
        this.granted.push(callback);
    }

    onError(callback: () => void): void {
        this.refused.push(callback);
    }

    listen(event: string, callback: RawListener): void {
        this.on(this.events, event).add(callback);
    }

    stopListening(event: string, callback: RawListener): void {
        this.on(this.events, event).delete(callback);
    }

    whisper(event: string, data: Record<string, unknown>): void {
        this.sent.push([event, data]);
    }

    listenForWhisper(event: string, callback: RawListener): void {
        this.on(this.whisperListeners, event).add(callback);
    }

    stopListeningForWhisper(event: string, callback: RawListener): void {
        this.on(this.whisperListeners, event).delete(callback);
    }

    grant(): void {
        this.granted.forEach((callback) => callback());
    }

    refuse(): void {
        this.refused.forEach((callback) => callback());
    }

    emit(event: string, payload: unknown): void {
        this.events.get(event)?.forEach((callback) => callback(payload));
    }

    emitWhisper(event: string, payload: unknown): void {
        this.whisperListeners.get(event)?.forEach((callback) => callback(payload));
    }

    private on(map: Map<string, Set<RawListener>>, event: string): Set<RawListener> {
        let set = map.get(event);
        if (set === undefined) {
            set = new Set();
            map.set(event, set);
        }

        return set;
    }
}

class FakeClient implements RealtimeClient {
    current: RealtimeConnectionState = 'connecting';
    readonly channels = new Map<string, FakeChannel>();
    readonly subscriptions: string[] = [];
    readonly left: string[] = [];
    reconnects = 0;
    disconnected = false;
    private readonly listeners: Array<(state: RealtimeConnectionState) => void> = [];

    constructor(readonly config: RealtimeSharedProps) {}

    state(): RealtimeConnectionState {
        return this.current;
    }

    onStateChange(callback: (state: RealtimeConnectionState) => void): void {
        this.listeners.push(callback);
    }

    subscribe(name: string): RealtimeChannelHandle {
        this.subscriptions.push(name);
        const channel = new FakeChannel();
        this.channels.set(name, channel);

        return channel;
    }

    leave(name: string): void {
        this.left.push(name);
    }

    reconnect(): void {
        this.reconnects++;
    }

    disconnect(): void {
        this.disconnected = true;
    }

    move(state: RealtimeConnectionState): void {
        this.current = state;
        this.listeners.forEach((callback) => callback(state));
    }

    channel(name: string): FakeChannel {
        const channel = this.channels.get(name);
        if (channel === undefined) {
            throw new Error(`Not subscribed to ${name}`);
        }

        return channel;
    }
}

const clients: FakeClient[] = [];
const factory = vi.fn(async (config: RealtimeSharedProps): Promise<RealtimeClient> => {
    const client = new FakeClient(config);
    clients.push(client);

    return client;
});

const scopes: EffectScope[] = [];

function listen(
    channel: string | null,
    listeners: Record<string, (payload: RealtimePayload) => void> = {}
): UseRealtimeChannelReturn {
    const scope = effectScope();
    scopes.push(scope);
    const result = scope.run(() => useRealtimeChannel(() => channel, listeners));
    if (result === undefined) {
        throw new Error('The scope did not run');
    }

    return result;
}

async function connected(): Promise<FakeClient> {
    await flushPromises();
    const client = clients.at(-1);
    if (client === undefined) {
        throw new Error('No client was created');
    }
    client.move('connected');
    await nextTick();

    return client;
}

describe('useRealtimeChannel', () => {
    beforeEach(() => {
        page.props = { realtime: { ...CONFIG }, auth: { user: { id: 'user-1' } } };
        clients.length = 0;
        factory.mockClear();
        setRealtimeClientFactory(factory);
    });

    afterEach(() => {
        scopes.splice(0).forEach((scope) => scope.stop());
        resetRealtimeConnection();
        setRealtimeClientFactory(null);
    });

    it('does not connect without real-time settings', async () => {
        page.props = { realtime: null, auth: { user: { id: 'user-1' } } };

        const { status, live } = listen('forum.discussion.1');
        await flushPromises();

        expect(factory).not.toHaveBeenCalled();
        expect(status.value).toBe('off');
        expect(live.value).toBe(false);
    });

    it('does not connect with incomplete settings', async () => {
        page.props = { realtime: { key: 'example-key', host: '' }, auth: null };

        const { status } = listen('forum.discussion.1');
        await flushPromises();

        expect(factory).not.toHaveBeenCalled();
        expect(status.value).toBe('off');
    });

    it('stays off without a channel and connects nothing', async () => {
        const { status } = listen(null);
        await flushPromises();

        expect(status.value).toBe('off');
        expect(clients[0]?.subscriptions ?? []).toEqual([]);
    });

    it('connects, is live once the server grants the channel and hands over the payload', async () => {
        const received: RealtimePayload[] = [];
        const { status, live } = listen('forum.discussion.1', {
            'post.created': (payload) => received.push(payload),
        });

        expect(status.value).toBe('connecting');
        const client = await connected();
        expect(factory).toHaveBeenCalledWith(CONFIG);
        expect(client.subscriptions).toEqual(['forum.discussion.1']);
        expect(status.value).toBe('connecting');

        client.channel('forum.discussion.1').grant();
        await nextTick();
        expect(status.value).toBe('live');
        expect(live.value).toBe(true);

        client.channel('forum.discussion.1').emit('post.created', { number: 42 });
        expect(received).toEqual([{ number: 42 }]);
        expect(Object.isFrozen(received[0])).toBe(true);
    });

    it('reports offline when the connection drops and live again after it reconnects', async () => {
        const { status } = listen('forum.discussion.1');
        const client = await connected();
        client.channel('forum.discussion.1').grant();
        await nextTick();

        client.move('unavailable');
        await nextTick();
        expect(status.value).toBe('offline');

        // Back online: the page asks the client to try again at once
        window.dispatchEvent(new Event('online'));
        expect(client.reconnects).toBe(1);

        client.move('connecting');
        await nextTick();
        expect(status.value).toBe('connecting');

        client.move('connected');
        await nextTick();
        // A drop loses the grant: connected is not enough until the server grants it again
        expect(status.value).toBe('connecting');

        client.channel('forum.discussion.1').grant();
        await nextTick();
        expect(status.value).toBe('live');
    });

    it('tries again when the tab becomes visible after a drop', async () => {
        listen('forum.discussion.1');
        const client = await connected();
        client.move('unavailable');

        document.dispatchEvent(new Event('visibilitychange'));

        expect(client.reconnects).toBe(1);
    });

    it('does not reconnect a connection that is fine', async () => {
        listen('forum.discussion.1');
        const client = await connected();

        window.dispatchEvent(new Event('online'));

        expect(client.reconnects).toBe(0);
    });

    it('reports offline when the server refuses the channel', async () => {
        const { status } = listen('forum.discussion.1');
        const client = await connected();

        client.channel('forum.discussion.1').refuse();
        await nextTick();

        expect(status.value).toBe('offline');
    });

    it('is offline when the client cannot be loaded and tries again once back online', async () => {
        factory.mockRejectedValueOnce(new Error('offline'));
        const { status } = listen('forum.discussion.1');
        await flushPromises();

        expect(status.value).toBe('offline');

        window.dispatchEvent(new Event('online'));
        const client = await connected();
        client.channel('forum.discussion.1').grant();
        await nextTick();

        expect(factory).toHaveBeenCalledTimes(2);
        expect(status.value).toBe('live');
    });

    it('subscribes once for two listeners and leaves when the last one stops', async () => {
        const first: RealtimePayload[] = [];
        const second: RealtimePayload[] = [];
        listen('forum.discussion.1', { 'post.created': (payload) => first.push(payload) });
        listen('forum.discussion.1', { 'post.created': (payload) => second.push(payload) });
        const client = await connected();

        expect(factory).toHaveBeenCalledTimes(1);
        expect(client.subscriptions).toEqual(['forum.discussion.1']);

        scopes[0]?.stop();
        client.channel('forum.discussion.1').emit('post.created', { number: 1 });
        expect(first).toEqual([]);
        expect(second).toEqual([{ number: 1 }]);
        expect(client.left).toEqual([]);

        scopes[1]?.stop();
        expect(client.left).toEqual(['forum.discussion.1']);
    });

    it('moves to the new channel when the name changes', async () => {
        const name = ref<string | null>('forum.discussion.1');
        const scope = effectScope();
        scopes.push(scope);
        scope.run(() => useRealtimeChannel(name));
        const client = await connected();

        name.value = 'forum.discussion.2';
        await nextTick();

        expect(client.left).toEqual(['forum.discussion.1']);
        expect(client.subscriptions).toEqual(['forum.discussion.1', 'forum.discussion.2']);
    });

    it('starts a new connection for another account and disconnects on logout', async () => {
        const { status } = listen('forum.discussion.1');
        const first = await connected();

        page.props = { realtime: { ...CONFIG }, auth: { user: { id: 'user-2' } } };
        await nextTick();
        expect(first.disconnected).toBe(true);
        const second = await connected();
        expect(second.subscriptions).toEqual(['forum.discussion.1']);

        page.props = { realtime: null, auth: null };
        await nextTick();
        expect(second.disconnected).toBe(true);
        expect(status.value).toBe('off');
    });

    it('whispers only while live and hands over the whispers of others', async () => {
        const typing: RealtimePayload[] = [];
        const { whisper, onWhisper } = listen('forum.discussion.1');
        const stop = onWhisper('typing', (payload) => typing.push(payload));
        const client = await connected();
        const channel = client.channel('forum.discussion.1');

        expect(whisper('typing', { userId: 'user-1' })).toBe(false);

        channel.grant();
        await nextTick();
        expect(whisper('typing', { userId: 'user-1' })).toBe(true);
        expect(channel.sent).toEqual([['typing', { userId: 'user-1' }]]);

        channel.emitWhisper('typing', { userId: 'user-2' });
        stop();
        channel.emitWhisper('typing', { userId: 'user-3' });
        expect(typing).toEqual([{ userId: 'user-2' }]);
    });
});
