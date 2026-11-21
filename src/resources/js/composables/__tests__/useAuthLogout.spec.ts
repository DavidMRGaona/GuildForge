import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

const page: { props: Record<string, unknown> } = { props: {} };
const post = vi.fn();
const forgetThisDevice = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    usePage: (): typeof page => page,
    router: { post: (...args: unknown[]): unknown => post(...args) },
}));

vi.mock('@/utils/webPush', () => ({
    forgetThisDevice: (userId: string): Promise<void> => forgetThisDevice(userId) as Promise<void>,
}));

const { useAuth } = await import('../useAuth');

describe('useAuth().logout', () => {
    beforeEach(() => {
        vi.useFakeTimers();
        post.mockReset();
        forgetThisDevice.mockReset();
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    it('forgets this browser for push before signing out', async () => {
        page.props = { webPush: { publicKey: 'key' }, auth: { user: { id: 'user-a' } } };
        const order: string[] = [];
        forgetThisDevice.mockImplementation(async () => void order.push('forget'));
        post.mockImplementation(() => void order.push('post'));

        useAuth().logout();
        await vi.runAllTimersAsync();

        expect(forgetThisDevice).toHaveBeenCalledWith('user-a');
        expect(order).toEqual(['forget', 'post']);
        expect(post).toHaveBeenCalledWith('/cerrar-sesion');
    });

    it('signs out anyway when forgetting the browser takes too long', async () => {
        page.props = { webPush: { publicKey: 'key' }, auth: { user: { id: 'user-a' } } };
        forgetThisDevice.mockReturnValue(new Promise(() => undefined));

        useAuth().logout();
        await vi.advanceTimersByTimeAsync(2000);

        expect(post).toHaveBeenCalledWith('/cerrar-sesion');
    });

    it('forgets this browser first, then signs out through the route a module gives', async () => {
        page.props = { webPush: { publicKey: 'key' }, auth: { user: { id: 'user-a' } } };
        const order: string[] = [];
        forgetThisDevice.mockImplementation(async () => void order.push('forget'));
        post.mockImplementation((url: string) => void order.push(`post ${url}`));

        useAuth().logout({ url: '/partidas/invitacion/abc/salir?volver=1' });
        await vi.runAllTimersAsync();

        expect(order).toEqual(['forget', 'post /partidas/invitacion/abc/salir?volver=1']);
    });

    it('takes an absolute address of this site as its path', async () => {
        page.props = { webPush: null };

        useAuth().logout({ url: `${window.location.origin}/partidas/salir` });
        await vi.runAllTimersAsync();

        expect(post).toHaveBeenCalledWith('/partidas/salir');
    });

    it('never sends the sign-out to another site', async () => {
        page.props = { webPush: null };

        for (const url of [
            'https://evil.test/cerrar-sesion',
            '//evil.test/cerrar-sesion',
            '/\\evil.test/cerrar-sesion',
            '/.//evil.test/cerrar-sesion',
            '/partidas/..//evil.test/cerrar-sesion',
            'javascript:alert(1)',
        ]) {
            post.mockReset();
            useAuth().logout({ url });
            await vi.runAllTimersAsync();

            expect(post).toHaveBeenCalledWith('/cerrar-sesion');
        }
    });

    it('signs out through the core when it gets a click event instead of options', async () => {
        page.props = { webPush: null };

        useAuth().logout(new MouseEvent('click') as unknown as { url?: string });
        await vi.runAllTimersAsync();

        expect(post).toHaveBeenCalledWith('/cerrar-sesion');
    });

    it('signs out at once while push is off', async () => {
        page.props = { webPush: null };

        useAuth().logout();
        await vi.runAllTimersAsync();

        expect(forgetThisDevice).not.toHaveBeenCalled();
        expect(post).toHaveBeenCalledWith('/cerrar-sesion');
    });
});
