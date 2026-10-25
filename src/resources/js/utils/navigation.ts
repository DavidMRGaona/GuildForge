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
