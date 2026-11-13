import { ref, shallowReactive } from 'vue';
import type { Ref } from 'vue';
import { createEchoClient } from './client';
import type {
    RawListener,
    RealtimeChannelHandle,
    RealtimeClient,
    RealtimeClientFactory,
    RealtimeConnectionState,
} from './client';
import type { RealtimeSharedProps, RealtimeStatus } from '@/types/realtime';

/**
 * One WebSocket per page, shared by every subscription (core#493). Channels are counted: two
 * components that listen to the same channel subscribe once, and it is left when the last one
 * stops. pusher-js reconnects on its own after a drop; on top of that the page tries again as soon
 * as the browser is back online or the tab visible. After each reconnection the server grants the
 * channels again, one request each.
 *
 * Without settings (no real time, nobody signed in) nothing is loaded and nothing connects.
 */

interface Binding {
    event: string;
    callback: RawListener;
    whisper: boolean;
}

interface ChannelEntry {
    users: number;
    handle: RealtimeChannelHandle | null;
    granted: Ref<boolean>;
    refused: Ref<boolean>;
    bindings: Set<Binding>;
}

type ManagerState = RealtimeConnectionState | 'off';

const state = ref<ManagerState>('off');
const channels = shallowReactive(new Map<string, ChannelEntry>());

let factory: RealtimeClientFactory = createEchoClient;
let client: RealtimeClient | null = null;
let settings: RealtimeSharedProps | null = null;
let settingsKey: string | null = null;
let generation = 0;
let listening = false;

function bindOne(entry: ChannelEntry, binding: Binding): void {
    if (entry.handle === null) {
        return;
    }
    if (binding.whisper) {
        entry.handle.listenForWhisper(binding.event, binding.callback);
    } else {
        entry.handle.listen(binding.event, binding.callback);
    }
}

function unbindOne(entry: ChannelEntry, binding: Binding): void {
    if (entry.handle === null) {
        return;
    }
    if (binding.whisper) {
        entry.handle.stopListeningForWhisper(binding.event, binding.callback);
    } else {
        entry.handle.stopListening(binding.event, binding.callback);
    }
}

function attach(name: string, entry: ChannelEntry): void {
    if (client === null || entry.handle !== null) {
        return;
    }

    const handle = client.subscribe(name);
    entry.handle = handle;
    entry.granted.value = false;
    entry.refused.value = false;

    handle.onSubscribed(() => {
        // A grant that arrives after the channel was left belongs to nobody
        if (channels.get(name) === entry && entry.handle === handle) {
            entry.granted.value = true;
            entry.refused.value = false;
        }
    });
    handle.onError(() => {
        if (channels.get(name) === entry && entry.handle === handle) {
            entry.granted.value = false;
            entry.refused.value = true;
        }
    });

    for (const binding of entry.bindings) {
        bindOne(entry, binding);
    }
}

function retryNow(): void {
    if (settings === null) {
        return;
    }
    if (client === null) {
        // Loading the client failed (offline when the page opened): try again
        if (state.value === 'failed') {
            start(settings);
        }

        return;
    }
    if (['unavailable', 'disconnected', 'failed'].includes(client.state())) {
        client.reconnect();
    }
}

function onVisibility(): void {
    if (document.visibilityState === 'visible') {
        retryNow();
    }
}

function listenToBrowser(on: boolean): void {
    if (typeof window === 'undefined' || on === listening) {
        return;
    }
    listening = on;
    if (on) {
        window.addEventListener('online', retryNow);
        document.addEventListener('visibilitychange', onVisibility);
    } else {
        window.removeEventListener('online', retryNow);
        document.removeEventListener('visibilitychange', onVisibility);
    }
}

function start(config: RealtimeSharedProps): void {
    const current = ++generation;
    state.value = 'connecting';
    listenToBrowser(true);

    factory(config).then(
        (created) => {
            if (current !== generation) {
                created.disconnect();

                return;
            }
            client = created;
            created.onStateChange((next) => {
                if (current !== generation) {
                    return;
                }
                state.value = next;
                if (next !== 'connected') {
                    // Subscriptions do not survive a drop: the server grants them again
                    for (const entry of channels.values()) {
                        entry.granted.value = false;
                    }
                }
            });
            state.value = created.state();
            for (const [name, entry] of channels) {
                attach(name, entry);
            }
        },
        () => {
            if (current === generation) {
                state.value = 'failed';
            }
        }
    );
}

function stop(): void {
    generation++;
    client?.disconnect();
    client = null;
    for (const entry of channels.values()) {
        entry.handle = null;
        entry.granted.value = false;
        entry.refused.value = false;
    }
    state.value = 'off';
    listenToBrowser(false);
}

/**
 * Connects with these settings, or disconnects with none. The same settings for the same
 * account keep the current connection; anything else starts a new one (logging in as someone
 * else must never keep the channels granted to the previous account).
 */
export function configureRealtime(config: RealtimeSharedProps | null, account: unknown): void {
    const key = config === null ? null : JSON.stringify([config, account]);
    if (key === settingsKey) {
        return;
    }

    stop();
    settings = config;
    settingsKey = key;
    if (config !== null) {
        start(config);
    }
}

export function isRealtimeConfigured(): boolean {
    return settings !== null;
}

/** Starts listening to a channel (counted). */
export function acquireChannel(name: string): void {
    let entry = channels.get(name);
    if (entry === undefined) {
        entry = {
            users: 0,
            handle: null,
            granted: ref(false),
            refused: ref(false),
            bindings: new Set(),
        };
        channels.set(name, entry);
    }
    entry.users++;
    attach(name, entry);
}

/** Stops listening; the channel is left when nobody listens any more. */
export function releaseChannel(name: string): void {
    const entry = channels.get(name);
    if (entry === undefined) {
        return;
    }
    entry.users--;
    if (entry.users > 0) {
        return;
    }
    for (const binding of entry.bindings) {
        unbindOne(entry, binding);
    }
    channels.delete(name);
    if (entry.handle !== null) {
        client?.leave(name);
    }
}

/** Binds a listener to a channel acquired before; returns what undoes it. */
export function bindChannel(
    name: string,
    event: string,
    callback: RawListener,
    whisper = false
): () => void {
    const entry = channels.get(name);
    if (entry === undefined) {
        return () => undefined;
    }
    const binding: Binding = { event, callback, whisper };
    entry.bindings.add(binding);
    bindOne(entry, binding);

    return () => {
        if (entry.bindings.delete(binding)) {
            unbindOne(entry, binding);
        }
    };
}

/** Where a channel stands for whoever listens to it. */
export function channelStatus(name: string | null): RealtimeStatus {
    const current = state.value;
    if (name === null || current === 'off') {
        return 'off';
    }
    const entry = channels.get(name);
    if (current === 'connected') {
        if (entry?.granted.value === true) {
            return 'live';
        }

        return entry?.refused.value === true ? 'offline' : 'connecting';
    }

    return current === 'initialized' || current === 'connecting' ? 'connecting' : 'offline';
}

/** Sends a client event to the other people on the channel; false when it is not live. */
export function whisperOn(name: string, event: string, data: Record<string, unknown>): boolean {
    const entry = channels.get(name);
    if (entry?.handle == null || channelStatus(name) !== 'live') {
        return false;
    }
    entry.handle.whisper(event, data);

    return true;
}

/** Only for tests: use a fake client. */
export function setRealtimeClientFactory(next: RealtimeClientFactory | null): void {
    factory = next ?? createEchoClient;
}

/** Only for tests: forget the connection and every channel. */
export function resetRealtimeConnection(): void {
    stop();
    channels.clear();
    settings = null;
    settingsKey = null;
}
