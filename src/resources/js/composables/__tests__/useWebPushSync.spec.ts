import { describe, expect, it, vi } from 'vitest';
import { nextTick, reactive } from 'vue';

const page = reactive<{
    props: { webPush: { publicKey: string } | null; auth: { user: { id: string } | null } };
}>({
    props: { webPush: null, auth: { user: null } },
});

const { syncThisDevice } = vi.hoisted(() => ({ syncThisDevice: vi.fn() }));

vi.mock('@inertiajs/vue3', () => ({ usePage: (): typeof page => page }));
vi.mock('@/utils/webPush', () => ({ syncThisDevice }));

const { useWebPushSync } = await import('../useWebPushSync');

describe('useWebPushSync', () => {
    it('checks this browser for each account that signs in on the tab, and only with push on', async () => {
        useWebPushSync();
        expect(syncThisDevice).not.toHaveBeenCalled();

        page.props = { webPush: { publicKey: 'key' }, auth: { user: { id: 'user-a' } } };
        await nextTick();
        page.props = { webPush: { publicKey: 'key' }, auth: { user: { id: 'user-b' } } };
        await nextTick();
        page.props = { webPush: null, auth: { user: null } };
        await nextTick();

        expect(syncThisDevice.mock.calls).toEqual([
            ['key', 'user-a'],
            ['key', 'user-b'],
        ]);
    });
});
