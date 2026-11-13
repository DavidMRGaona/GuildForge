import { csrfHeaders } from '@/utils/csrf';
import type { RealtimeSharedProps } from '@/types/realtime';

/** The socket as the connection manager sees it (the states of pusher-js). */
export type RealtimeConnectionState =
    'initialized' | 'connecting' | 'connected' | 'unavailable' | 'disconnected' | 'failed';

export type RawListener = (payload: unknown) => void;

/** One private channel of the client. Every binding stays until it is undone. */
export interface RealtimeChannelHandle {
    /** Called each time the server grants the channel (again after every reconnection). */
    onSubscribed: (callback: () => void) => void;
    /** Called when the server refuses it (403, or the request failed). */
    onError: (callback: () => void) => void;
    listen: (event: string, callback: RawListener) => void;
    stopListening: (event: string, callback: RawListener) => void;
    whisper: (event: string, data: Record<string, unknown>) => void;
    listenForWhisper: (event: string, callback: RawListener) => void;
    stopListeningForWhisper: (event: string, callback: RawListener) => void;
}

/** The WebSocket client, behind an interface so tests use a fake one. */
export interface RealtimeClient {
    state: () => RealtimeConnectionState;
    onStateChange: (callback: (state: RealtimeConnectionState) => void) => void;
    /** A private channel; the name has no `private-` prefix. */
    subscribe: (name: string) => RealtimeChannelHandle;
    leave: (name: string) => void;
    /** Try to connect again now (back online, back to the tab). */
    reconnect: () => void;
    disconnect: () => void;
}

export type RealtimeClientFactory = (config: RealtimeSharedProps) => Promise<RealtimeClient>;

const KNOWN_STATES: readonly RealtimeConnectionState[] = [
    'initialized',
    'connecting',
    'connected',
    'unavailable',
    'disconnected',
    'failed',
];

function toState(value: unknown): RealtimeConnectionState {
    return KNOWN_STATES.includes(value as RealtimeConnectionState)
        ? (value as RealtimeConnectionState)
        : 'disconnected';
}

/** Event names are exact (`post.created`): the leading dot tells Echo not to add a namespace. */
function exact(event: string): string {
    return event.startsWith('.') ? event : `.${event}`;
}

/**
 * Asks the site to sign a private subscription, with the current CSRF cookie (the meta tag Echo
 * would read goes stale after logging in or out) and the session cookie.
 */
async function authorizeChannel(
    endpoint: string,
    socketId: string,
    channelName: string
): Promise<{ auth: string }> {
    const response = await fetch(endpoint, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...csrfHeaders(),
        },
        credentials: 'same-origin',
        body: JSON.stringify({ socket_id: socketId, channel_name: channelName }),
    });

    if (!response.ok) {
        throw new Error(`The subscription to ${channelName} was refused (${response.status})`);
    }

    const body = (await response.json()) as { auth?: unknown };
    if (typeof body.auth !== 'string') {
        throw new Error(`The subscription to ${channelName} came back without a signature`);
    }

    return { auth: body.auth };
}

/**
 * The real client: laravel-echo with pusher-js (the protocol Reverb speaks). Both are loaded only
 * here, when the page has real time, so a site without it never downloads them.
 */
export const createEchoClient: RealtimeClientFactory = async (config) => {
    const [{ default: Echo }, { default: Pusher }] = await Promise.all([
        import('laravel-echo'),
        import('pusher-js'),
    ]);

    const echo = new Echo({
        broadcaster: 'reverb',
        key: config.key,
        wsHost: config.host,
        wsPort: config.port,
        wssPort: config.port,
        forceTLS: config.tls,
        // One scheme, the same one `RealtimeClientConfigDTO::origin()` gives the CSP's connect-src
        // (both read `tls`): trying the other one is blocked by the CSP and delays reconnecting.
        enabledTransports: config.tls ? ['wss'] : ['ws'],
        Pusher,
        withoutInterceptors: true,
        channelAuthorization: {
            customHandler: (
                params: { socketId: string; channelName: string },
                callback: (error: Error | null, data: { auth: string } | null) => void
            ): void => {
                authorizeChannel(config.authEndpoint, params.socketId, params.channelName).then(
                    (data) => callback(null, data),
                    (error: unknown) =>
                        callback(error instanceof Error ? error : new Error(String(error)), null)
                );
            },
        },
    });

    const connection = echo.connector.pusher.connection;

    return {
        state: () => toState(connection.state),
        onStateChange: (callback): void => {
            connection.bind('state_change', (change: { current?: unknown }) =>
                callback(toState(change.current))
            );
        },
        subscribe: (name): RealtimeChannelHandle => {
            const channel = echo.private(name);

            return {
                onSubscribed: (callback): void => {
                    channel.subscribed(callback);
                },
                onError: (callback): void => {
                    channel.error(() => callback());
                },
                listen: (event, callback): void => {
                    channel.listen(exact(event), callback);
                },
                stopListening: (event, callback): void => {
                    channel.stopListening(exact(event), callback);
                },
                whisper: (event, data): void => {
                    channel.whisper(event, data);
                },
                listenForWhisper: (event, callback): void => {
                    channel.listenForWhisper(event, callback);
                },
                stopListeningForWhisper: (event, callback): void => {
                    channel.stopListeningForWhisper(event, callback);
                },
            };
        },
        leave: (name) => echo.leave(name),
        reconnect: () => echo.connector.pusher.connect(),
        disconnect: () => echo.disconnect(),
    };
};
