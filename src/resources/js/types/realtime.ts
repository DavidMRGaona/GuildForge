/**
 * Shared prop `realtime` (core#493): where the browser opens the WebSocket. Null when the
 * installation has no real time or nobody is signed in; the pages then keep polling.
 */
export interface RealtimeSharedProps {
    /** Public application key (never the secret). */
    key: string;
    host: string;
    port: number;
    tls: boolean;
    /** Signs each private subscription on the server (`/broadcasting/auth`). */
    authEndpoint: string;
}

/**
 * Where a subscription stands:
 * - `off`: no real time on this page (not configured, no session or no channel): poll.
 * - `connecting`: opening the socket or waiting for the server to grant the channel.
 * - `live`: connected and subscribed: messages arrive, the page may stop polling.
 * - `offline`: the connection was lost or the channel refused: poll until it is `live` again.
 */
export type RealtimeStatus = 'off' | 'connecting' | 'live' | 'offline';

/** What a message carries: ids and numbers only, never content (core rule). */
export type RealtimePayload = Readonly<Record<string, unknown>>;

export type RealtimeListener = (payload: RealtimePayload) => void;
