import { useI18n } from 'vue-i18n';
import { useImpersonationReadOnly } from '@/composables/useImpersonationReadOnly';
import { useVerificationGate } from '@/composables/useVerificationGate';
import { csrfHeaders } from '@/utils/csrf';

export type TwoFactorApiResult<T> =
    | { ok: true; data: T }
    | {
          ok: false;
          /** Error per field (`current_password`, `code`) from a 422. */
          errors: Record<string, string>;
          /** Text for anything that is not about one field; null when it was already shown. */
          message: string | null;
      };

interface ErrorBody {
    message?: unknown;
    errors?: Record<string, unknown>;
}

function firstMessage(value: unknown): string | null {
    if (Array.isArray(value) && typeof value[0] === 'string') {
        return value[0];
    }

    return typeof value === 'string' ? value : null;
}

/**
 * The requests of «Seguridad» (core#769): JSON, so a secret or recovery codes only live in the
 * answer that creates them. Failures arrive as data: errors per field for the form, or a text.
 * An account that must verify its email goes to the notice and a read-only impersonation shows
 * its own refusal, both already handled (`message: null`).
 */
export function useTwoFactorApi(): {
    send: <T>(
        method: 'POST' | 'DELETE',
        url: string,
        body?: Record<string, string>
    ) => Promise<TwoFactorApiResult<T>>;
} {
    const { t } = useI18n();
    const gate = useVerificationGate();
    const readOnly = useImpersonationReadOnly();

    async function send<T>(
        method: 'POST' | 'DELETE',
        url: string,
        body?: Record<string, string>
    ): Promise<TwoFactorApiResult<T>> {
        try {
            const response = await fetch(url, {
                method,
                headers: {
                    Accept: 'application/json',
                    ...(body ? { 'Content-Type': 'application/json' } : {}),
                    ...csrfHeaders(),
                },
                credentials: 'same-origin',
                ...(body ? { body: JSON.stringify(body) } : {}),
            });

            if (gate.isVerificationRequired(response)) {
                gate.goToNotice();

                return { ok: false, errors: {}, message: null };
            }

            if (await readOnly.notifyRefusal(response)) {
                return { ok: false, errors: {}, message: null };
            }

            if (response.ok) {
                return {
                    ok: true,
                    data: (response.status === 204 ? undefined : await response.json()) as T,
                };
            }

            if (response.status === 429) {
                return { ok: false, errors: {}, message: t('auth.profile.twoFactor.tooMany') };
            }

            const failure = (await response.json().catch(() => ({}))) as ErrorBody;
            const errors: Record<string, string> = {};
            for (const [field, value] of Object.entries(failure.errors ?? {})) {
                const text = firstMessage(value);
                if (text !== null) {
                    errors[field] = text;
                }
            }

            return {
                ok: false,
                errors,
                message:
                    Object.keys(errors).length > 0
                        ? null
                        : (firstMessage(failure.message) ?? t('auth.profile.twoFactor.error')),
            };
        } catch {
            return { ok: false, errors: {}, message: t('auth.profile.twoFactor.error') };
        }
    }

    return { send };
}
