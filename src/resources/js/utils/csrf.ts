/**
 * CSRF credentials for requests this app sends by hand, outside Inertia.
 *
 * The obvious source, `<meta name="csrf-token">`, is a trap here: it is rendered
 * once with the root document and Inertia never re-renders the head, so from the
 * moment anything regenerates the session — logging in, registering, logging out —
 * it still carries the token of the session that is gone. Requests sent with it
 * come back as 419 "CSRF token mismatch." while the page looks perfectly fine.
 *
 * Laravel refreshes the XSRF-TOKEN cookie on every response instead, which is why
 * Inertia's own requests never hit this: axios reads that cookie. Read the same
 * cookie and the token is always the current one.
 */

const XSRF_COOKIE = 'XSRF-TOKEN';

function readXsrfCookie(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

    return match?.[1] ? decodeURIComponent(match[1]) : null;
}

/**
 * Headers that authenticate a same-origin write request.
 *
 * Laravel decrypts `X-XSRF-TOKEN`, so the cookie value travels as-is. The meta tag
 * is kept as a last resort for the case where the cookie is missing entirely — a
 * stale token still beats no token, since without either the request is refused
 * outright.
 */
export function csrfHeaders(): Record<string, string> {
    const cookieToken = readXsrfCookie();

    if (cookieToken !== null) {
        return { 'X-XSRF-TOKEN': cookieToken };
    }

    const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    return metaToken ? { 'X-CSRF-TOKEN': metaToken } : {};
}

/**
 * A rejected CSRF token surfaces as 419, which Laravel reports as the untranslated
 * "CSRF token mismatch.". Callers use this to show something a visitor can act on.
 */
export function isCsrfFailure(response: Response): boolean {
    return response.status === 419;
}
