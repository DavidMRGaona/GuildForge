function trimTrailingSlash(path: string): string {
    return path.length > 1 && path.endsWith('/') ? path.slice(0, -1) : path;
}

/**
 * Whether a menu link points at the current page.
 *
 * The server sends absolute hrefs (route() output) while Inertia's page.url is a path,
 * so both are reduced to their pathname on the site's origin. A link stays active on
 * the pages below it, matched by whole segments ('/eventos' is not '/eventos-pasados');
 * the home link only on the home page. Links to other sites and '#' placeholders
 * (parents without a URL of their own) are never active.
 */
export function isActiveHref(href: string, currentUrl: string, origin: string): boolean {
    if (href === '' || href.startsWith('#')) {
        return false;
    }

    let target: URL;
    let current: URL;
    try {
        target = new URL(href, origin);
        current = new URL(currentUrl, origin);
    } catch {
        return false;
    }

    if (target.origin !== current.origin) {
        return false;
    }

    const targetPath = trimTrailingSlash(target.pathname);
    const currentPath = trimTrailingSlash(current.pathname);

    if (targetPath === '/') {
        return currentPath === '/';
    }

    return currentPath === targetPath || currentPath.startsWith(`${targetPath}/`);
}

/**
 * Where to visit with Inertia when a click on server-rendered HTML (v-html) lands on an internal
 * link, or null to leave it to the browser: a plain <a> there reloads the whole document.
 *
 * The browser keeps clicks with a modifier or another button, links that open elsewhere or
 * download, links to other sites, anchors on the current page, and the excluded paths and the
 * pages below them (the panel is not an Inertia app).
 */
export function internalLinkVisit(
    event: MouseEvent,
    currentUrl: string,
    excludedPaths: readonly string[]
): string | null {
    if (
        event.defaultPrevented ||
        event.button !== 0 ||
        event.metaKey ||
        event.ctrlKey ||
        event.shiftKey ||
        event.altKey
    ) {
        return null;
    }

    const link = event.target instanceof Element ? event.target.closest('a') : null;
    if (
        link === null ||
        !link.hasAttribute('href') ||
        (link.target !== '' && link.target !== '_self') ||
        link.hasAttribute('download')
    ) {
        return null;
    }

    let target: URL;
    let current: URL;
    try {
        current = new URL(currentUrl);
        target = new URL(link.getAttribute('href') ?? '', current);
    } catch {
        return null;
    }

    if (target.origin !== current.origin) {
        return null;
    }

    if (
        target.pathname === current.pathname &&
        target.search === current.search &&
        target.hash !== ''
    ) {
        return null;
    }

    const path = trimTrailingSlash(target.pathname);
    if (excludedPaths.some((excluded) => path === excluded || path.startsWith(`${excluded}/`))) {
        return null;
    }

    return `${target.pathname}${target.search}${target.hash}`;
}
