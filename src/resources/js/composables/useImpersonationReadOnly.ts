import { useI18n } from 'vue-i18n';
import { useNotifications } from '@/composables/useNotifications';

/** `error` field of the JSON 403 sent by BlockSiteWritesWhenImpersonatingReadOnly. */
export const IMPERSONATION_READ_ONLY_ERROR = 'impersonation_read_only';

/** Header the same 403 carries (value `1`), readable before parsing the body. */
export const IMPERSONATION_READ_ONLY_HEADER = 'X-Impersonation-Read-Only';

export interface ImpersonationReadOnly {
    /**
     * True when a failed request was refused because an administrator is viewing the site as
     * another account in «Solo mirar» (nothing is saved). Accepts a fetch() Response, its parsed
     * JSON body, an axios-like error (`{ response: { status, data } }`) and the response Inertia
     * hands to `onHttpException` (`{ status, data }`).
     */
    isReadOnlyRefusal: (error: unknown) => boolean;
    /**
     * Shows the refusal notice when `error` is one and tells so. For a fetch() Response whose
     * body was not read yet, use `notifyRefusal(response)`, which reads a copy of it.
     */
    notifyIfReadOnly: (error: unknown) => boolean;
    /** Same as `notifyIfReadOnly`, but also uses the server's text (it names the account). */
    notifyRefusal: (response: Response) => Promise<boolean>;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isFetchResponse(value: unknown): value is Response {
    return typeof Response !== 'undefined' && value instanceof Response;
}

function matches(error: unknown, depth: number): boolean {
    if (depth > 3) {
        return false;
    }

    if (isFetchResponse(error)) {
        return error.status === 403 && error.headers.get(IMPERSONATION_READ_ONLY_HEADER) === '1';
    }

    // Inertia leaves a body it could not parse as a string
    if (typeof error === 'string') {
        try {
            return matches(JSON.parse(error) as unknown, depth + 1);
        } catch {
            return false;
        }
    }

    if (!isRecord(error)) {
        return false;
    }

    // Parsed JSON body
    if (error.error === IMPERSONATION_READ_ONLY_ERROR) {
        return true;
    }

    // Response-like object: axios `error.response` or the response of Inertia's onHttpException
    if ('data' in error && ('status' in error || 'headers' in error)) {
        if (typeof error.status === 'number' && error.status !== 403) {
            return false;
        }

        return matches(error.data, depth + 1);
    }

    // axios-like error
    if ('response' in error) {
        return matches(error.response, depth + 1);
    }

    return false;
}

/**
 * Recognises the 403 that «Solo mirar» gives to every site submission (by its `error` code and
 * header, never by the translated text) so a page that sends JSON can say that nothing was saved
 * instead of showing its generic failure. Page visits (Inertia) need nothing: the server goes
 * back with the notice as a flash message.
 *
 * @example
 * ```ts
 * const readOnly = useImpersonationReadOnly();
 * const response = await fetch(url, { method: 'POST', headers: { Accept: 'application/json' } });
 * if (await readOnly.notifyRefusal(response)) {
 *     return; // the notice is already on screen
 * }
 * ```
 */
export function useImpersonationReadOnly(): ImpersonationReadOnly {
    const { t } = useI18n();
    const notifications = useNotifications();

    const notify = (message?: string): void => {
        notifications.error(message ?? t('impersonation.readOnlyNotice'));
    };

    return {
        isReadOnlyRefusal: (error: unknown): boolean => matches(error, 0),
        notifyIfReadOnly: (error: unknown): boolean => {
            if (!matches(error, 0)) {
                return false;
            }

            notify();

            return true;
        },
        notifyRefusal: async (response: Response): Promise<boolean> => {
            if (!matches(response, 0)) {
                return false;
            }

            let message: string | undefined;
            try {
                const body = (await response.clone().json()) as unknown;
                if (isRecord(body) && typeof body.message === 'string' && body.message !== '') {
                    message = body.message;
                }
            } catch {
                // Without a readable body the generic notice says the same
            }

            notify(message);

            return true;
        },
    };
}
