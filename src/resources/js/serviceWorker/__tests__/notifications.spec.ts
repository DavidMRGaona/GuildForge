import { describe, expect, it } from 'vitest';
import {
    fallbackNotification,
    findWindowAt,
    hasFocusedWindow,
    noticeIdFrom,
    noticeNotification,
    notificationTargetUrl,
} from '../notifications';

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
        expect(notificationTargetUrl({ url: '/\\evil.test/x' }, ORIGIN, '/avisos')).toBe(
            'https://example.test/avisos'
        );
        expect(notificationTargetUrl({ url: '/foro\\hilo' }, ORIGIN, '/avisos')).toBe(
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

describe('noticeIdFrom', () => {
    it('reads the notice id, the only thing a push message carries', () => {
        expect(noticeIdFrom({ id: '0193a6b2-7c1d-7e3f-8a9b-0c1d2e3f4a5b' })).toBe(
            '0193a6b2-7c1d-7e3f-8a9b-0c1d2e3f4a5b'
        );
    });

    it('ignores anything that is not a notice id', () => {
        for (const payload of [
            null,
            'abc',
            { id: 42 },
            { id: '../avisos' },
            { id: 'x'.repeat(36) },
        ]) {
            expect(noticeIdFrom(payload)).toBeNull();
        }
    });
});

describe('hasFocusedWindow', () => {
    it('is true while a tab of the site is visible and focused: the bell already says it', () => {
        expect(
            hasFocusedWindow([
                { focused: false, visibilityState: 'hidden' },
                { focused: true, visibilityState: 'visible' },
            ])
        ).toBe(true);
    });

    it('is false for tabs in the background or behind another window', () => {
        expect(
            hasFocusedWindow([
                { focused: false, visibilityState: 'visible' },
                { focused: true, visibilityState: 'hidden' },
            ])
        ).toBe(false);
    });
});

describe('noticeNotification', () => {
    const settings = {
        fallbackTitle: 'Tienes un aviso nuevo',
        fallbackBody: 'Asociación de ejemplo',
        fallbackPath: '/avisos',
        icon: '/icon.png',
    };
    const id = '0193a6b2-7c1d-7e3f-8a9b-0c1d2e3f4a5b';

    it('shows the notice the site answered, grouped by its tag', () => {
        expect(
            noticeNotification(
                {
                    title: 'Ana respondió',
                    body: 'Nos vemos',
                    url: `/avisos/${id}`,
                    tag: `notice-${id}`,
                },
                settings,
                ORIGIN
            )
        ).toEqual({
            title: 'Ana respondió',
            options: {
                body: 'Nos vemos',
                icon: '/icon.png',
                tag: `notice-${id}`,
                renotify: true,
                data: { url: `/avisos/${id}` },
            },
        });
    });

    it('shows the icon the site answered, or the one it was installed with', () => {
        const answered = noticeNotification(
            {
                title: 'Aviso',
                url: '/avisos',
                tag: 'notice-1',
                icon: 'https://res.cloudinary.com/demo/i.png',
            },
            settings,
            ORIGIN
        );
        const forged = noticeNotification(
            { title: 'Aviso', url: '/avisos', tag: 'notice-1', icon: 'javascript:alert(1)' },
            settings,
            ORIGIN
        );

        expect(answered?.options.icon).toBe('https://res.cloudinary.com/demo/i.png');
        expect(forged?.options.icon).toBe('/icon.png');
    });

    it('accepts the tag of a group, as long as a group key can be', () => {
        const tag = `notice-forum.topic:${'a'.repeat(180)}`;

        expect(
            noticeNotification({ title: 'Aviso', url: '/avisos', tag }, settings, ORIGIN)?.options
                .tag
        ).toBe(tag);
    });

    it('leaves out an empty body', () => {
        const request = noticeNotification(
            { title: 'Aviso', body: null, url: '/avisos', tag: 'notice-1' },
            settings,
            ORIGIN
        );

        expect(request?.options).not.toHaveProperty('body');
    });

    it('refuses an answer it cannot trust, so the generic notice shows instead', () => {
        for (const json of [
            null,
            { body: 'x', url: '/avisos', tag: 't' },
            { title: '', url: '/avisos', tag: 't' },
            { title: 'x', url: 'https://evil.test/', tag: 't' },
            { title: 'x', url: '/\\evil.test', tag: 't' },
            { title: 'x', url: '/avisos', tag: 3 },
        ]) {
            expect(noticeNotification(json, settings, ORIGIN)).toBeNull();
        }
    });
});
