import { afterEach, describe, expect, it } from 'vitest';
import { internalLinkVisit } from '../navigation';

const current = 'https://guild.test/ayuda/core.signup';

/**
 * Clicks an element inside a container that decides as the guide body does, while the event
 * is still being dispatched, and then cancels it so the test environment does not navigate.
 */
function visitOnClick(
    html: string,
    excluded: string[] = [],
    init: MouseEventInit = {},
    selector = 'a',
    before?: (target: Element) => void
): string | null {
    document.body.innerHTML = `<div>${html}</div>`;
    const container = document.body.querySelector('div');
    const target = document.body.querySelector(selector);
    if (container === null || target === null) {
        throw new Error(`Nothing matches ${selector}`);
    }
    before?.(target);

    let visit: string | null = null;
    container.addEventListener('click', (event) => {
        visit = internalLinkVisit(event, current, excluded);
        event.preventDefault();
    });
    target.dispatchEvent(
        new MouseEvent('click', { bubbles: true, cancelable: true, button: 0, ...init })
    );

    return visit;
}

describe('internalLinkVisit', () => {
    afterEach(() => {
        document.body.innerHTML = '';
    });

    it('returns the path of an internal link', () => {
        expect(
            visitOnClick('<a href="/partidas/crear?evento=1#top">Crear mesa</a>', ['/admin'])
        ).toBe('/partidas/crear?evento=1#top');
    });

    it('accepts an absolute href on the same origin', () => {
        expect(visitOnClick('<a href="https://guild.test/calendario">Calendario</a>')).toBe(
            '/calendario'
        );
    });

    it('finds the link from an element inside it', () => {
        expect(
            visitOnClick('<a href="/calendario"><strong>Ver</strong></a>', [], {}, 'strong')
        ).toBe('/calendario');
    });

    it('ignores clicks outside links', () => {
        expect(visitOnClick('<p>Texto</p>', [], {}, 'p')).toBeNull();
    });

    it.each([
        ['ctrlKey', { ctrlKey: true }],
        ['metaKey', { metaKey: true }],
        ['shiftKey', { shiftKey: true }],
        ['altKey', { altKey: true }],
        ['middle button', { button: 1 }],
    ])('leaves %s clicks to the browser', (_, init: MouseEventInit) => {
        expect(visitOnClick('<a href="/calendario">Calendario</a>', [], init)).toBeNull();
    });

    it('leaves links that open elsewhere or download to the browser', () => {
        expect(visitOnClick('<a href="/calendario" target="_blank">x</a>')).toBeNull();
        expect(visitOnClick('<a href="/archivo" download>x</a>')).toBeNull();
    });

    it('leaves links to other sites to the browser', () => {
        expect(visitOnClick('<a href="https://example.org/calendario">Fuera</a>')).toBeNull();
    });

    it('leaves the excluded paths and the pages below them to the browser', () => {
        expect(visitOnClick('<a href="/admin">Panel</a>', ['/admin'])).toBeNull();
        expect(visitOnClick('<a href="/admin/ayuda">Panel</a>', ['/admin'])).toBeNull();
        expect(visitOnClick('<a href="/administracion">x</a>', ['/admin'])).toBe('/administracion');
    });

    it('leaves anchors on the current page to the browser', () => {
        expect(visitOnClick('<a href="#campos">Campos</a>')).toBeNull();
    });

    it('ignores clicks something else already handled', () => {
        const visit = visitOnClick('<a href="/calendario">Calendario</a>', [], {}, 'a', (link) => {
            link.addEventListener('click', (e) => e.preventDefault());
        });

        expect(visit).toBeNull();
    });
});
