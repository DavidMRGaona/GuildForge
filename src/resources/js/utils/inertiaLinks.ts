/**
 * Paths of this site that are not Inertia pages: the panel, health and framework endpoints, and files
 * (an extension in the last segment). A client-side visit to them would load a JSON error or a
 * download into the page, so they must be plain links.
 */
const NON_PAGE_PREFIXES = [
    '/admin',
    '/up',
    '/livewire',
    '/broadcasting',
    '/storage',
    '/build',
    '/api',
];

export function isAbsoluteUrl(href: string): boolean {
    return /^[a-z][a-z0-9+.-]*:/i.test(href) || href.startsWith('//');
}

/**
 * Whether the link should be followed by Inertia: a path of the site that is a page.
 */
export function isInertiaPage(href: string): boolean {
    if (isAbsoluteUrl(href) || !href.startsWith('/')) {
        return false;
    }

    const path = (href.split(/[?#]/)[0] ?? '').toLowerCase();

    if (NON_PAGE_PREFIXES.some((prefix) => path === prefix || path.startsWith(`${prefix}/`))) {
        return false;
    }

    return !/\.[a-z0-9]{1,8}$/.test(path.split('/').pop() ?? '');
}
