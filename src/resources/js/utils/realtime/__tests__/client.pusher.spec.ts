import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import type { RealtimeClient } from '../client';
import type { RealtimeSharedProps } from '@/types/realtime';

// Real laravel-echo and pusher-js: only the browser's WebSocket is fake. Under Vitest `pusher-js`
// resolves to its Node build, which does not use the global WebSocket; the page gets the web one.
vi.mock('pusher-js', async () => import('pusher-js/dist/web/pusher.js'));

import { createEchoClient } from '../client';

const opened: string[] = [];

class FakeWebSocket {
    static readonly CONNECTING = 0;
    static readonly OPEN = 1;
    static readonly CLOSING = 2;
    static readonly CLOSED = 3;

    readyState = FakeWebSocket.CONNECTING;
    onopen: (() => void) | null = null;
    onclose: (() => void) | null = null;
    onerror: (() => void) | null = null;
    onmessage: (() => void) | null = null;

    constructor(url: string) {
        opened.push(url);
    }

    send(): void {}

    close(): void {
        this.readyState = FakeWebSocket.CLOSED;
    }
}

const BASE: RealtimeSharedProps = {
    key: 'example-key',
    host: 'example.test',
    port: 443,
    tls: true,
    authEndpoint: '/broadcasting/auth',
};

describe('createEchoClient with the real pusher-js', () => {
    let client: RealtimeClient | null = null;

    beforeEach(() => {
        opened.length = 0;
        vi.stubGlobal('WebSocket', FakeWebSocket);
    });

    afterEach(() => {
        client?.disconnect();
        client = null;
        vi.unstubAllGlobals();
    });

    it('opens a wss socket on a site with TLS', async () => {
        client = await createEchoClient(BASE);

        await vi.waitFor(() => expect(opened).toHaveLength(1));
        expect(opened[0]).toMatch(/^wss:\/\/example\.test(:443)?\/app\/example-key\?/);
        expect(client.state()).not.toBe('failed');
    });

    it('opens a ws socket on a site without TLS', async () => {
        client = await createEchoClient({ ...BASE, port: 8080, tls: false });

        await vi.waitFor(() => expect(opened).toHaveLength(1));
        expect(opened[0]).toMatch(/^ws:\/\/example\.test:8080\/app\/example-key\?/);
        expect(client.state()).not.toBe('failed');
    });

    it('never tries the other scheme, which the CSP would block', async () => {
        client = await createEchoClient(BASE);

        await vi.waitFor(() => expect(opened).toHaveLength(1));
        // The fake socket never opens: give pusher-js time to fall back if it were going to
        await new Promise((resolve) => setTimeout(resolve, 300));
        expect(opened.every((url) => url.startsWith('wss://'))).toBe(true);
    });
});
