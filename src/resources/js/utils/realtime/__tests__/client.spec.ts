import { beforeEach, describe, expect, it, vi } from 'vitest';
import type { RealtimeSharedProps } from '@/types/realtime';

const received = vi.hoisted(() => ({ options: null as Record<string, unknown> | null }));

vi.mock('laravel-echo', () => ({
    default: class FakeEcho {
        connector = { pusher: { connection: { state: 'initialized', bind: vi.fn() } } };

        constructor(options: Record<string, unknown>) {
            received.options = options;
        }
    },
}));

vi.mock('pusher-js', () => ({ default: class FakePusher {} }));

import { createEchoClient } from '../client';

const BASE: RealtimeSharedProps = {
    key: 'example-key',
    host: 'example.test',
    port: 443,
    tls: true,
    authEndpoint: '/broadcasting/auth',
};

describe('createEchoClient transports', () => {
    beforeEach(() => {
        received.options = null;
    });

    it('only tries wss on a site with TLS', async () => {
        await createEchoClient(BASE);

        expect(received.options).toMatchObject({
            forceTLS: true,
            enabledTransports: ['wss'],
            wsHost: 'example.test',
            wssPort: 443,
        });
    });

    it('only tries ws on a site without TLS, so the CSP never blocks a wss attempt', async () => {
        await createEchoClient({ ...BASE, port: 8080, tls: false });

        expect(received.options).toMatchObject({
            forceTLS: false,
            enabledTransports: ['ws'],
            wsHost: 'example.test',
            wsPort: 8080,
        });
    });
});
