import { describe, expect, it } from 'vitest';
import { fallbackNotification, findWindowAt, notificationTargetUrl } from '../notifications';

const ORIGIN = 'https://example.test';

describe('fallbackNotification', () => {
    it('says there is a new notice and opens the list of notices', () => {
        expect(
            fallbackNotification({
                fallbackTitle: 'Tienes un aviso nuevo',
                fallbackBody: 'Asociación de ejemplo',
                fallbackPath: '/avisos',
                icon: '/icon.png',
            })
        ).toEqual({
            title: 'Tienes un aviso nuevo',
            options: { body: 'Asociación de ejemplo', icon: '/icon.png', data: { url: '/avisos' } },
        });
    });

    it('leaves the icon out when the site has none', () => {
        const { options } = fallbackNotification({
            fallbackTitle: 'Aviso',
            fallbackBody: '',
            fallbackPath: '/avisos',
            icon: '',
        });

        expect(options).not.toHaveProperty('icon');
    });
});

describe('notificationTargetUrl', () => {
    it('opens the address the notification carries on this site', () => {
        expect(notificationTargetUrl({ url: '/foro/hilo-1#respuesta-3' }, ORIGIN, '/avisos')).toBe(
            'https://example.test/foro/hilo-1#respuesta-3'
        );
    });

    it('opens the fallback for another site, another scheme or no address', () => {
        expect(notificationTargetUrl({ url: 'https://evil.test/x' }, ORIGIN, '/avisos')).toBe(
            'https://example.test/avisos'
        );
        expect(notificationTargetUrl({ url: '//evil.test/x' }, ORIGIN, '/avisos')).toBe(
            'https://example.test/avisos'
        );
        expect(notificationTargetUrl({ url: 'javascript:alert(1)' }, ORIGIN, '/avisos')).toBe(
            'https://example.test/avisos'
        );
        expect(notificationTargetUrl(null, ORIGIN, '/avisos')).toBe('https://example.test/avisos');
        expect(notificationTargetUrl({ url: 42 }, ORIGIN, '/avisos')).toBe(
            'https://example.test/avisos'
        );
    });
});

describe('findWindowAt', () => {
    it('finds a window already showing the address, whatever its fragment', () => {
        const windows = [
            { url: 'https://example.test/' },
            { url: 'https://example.test/avisos#arriba' },
        ];

        expect(findWindowAt(windows, 'https://example.test/avisos')).toBe(windows[1]);
    });

    it('finds nothing when no window shows it', () => {
        expect(
            findWindowAt([{ url: 'https://example.test/' }], 'https://example.test/avisos')
        ).toBeUndefined();
    });
});
