import { router } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';
import { useI18n } from 'vue-i18n';
import { useAuth } from '@/composables/useAuth';
import { routes } from '@/composables/useRoutes';

/**
 * What a blocked form or button was going to do; each one has its own translated reason
 * (`auth.verificationGate.reasons.<action>`).
 */
export type VerificationGateAction =
    | 'register'
    | 'cancelRegistration'
    | 'book'
    | 'cancelBooking'
    | 'createTable'
    | 'createCampaign'
    | 'checkIn'
    | 'withdraw'
    | 'changePassword'
    | 'form';

/** `error` field of the JSON 403 sent by EnsureEmailIsVerifiedWhenRequired. */
export const VERIFICATION_REQUIRED_ERROR = 'email_verification_required';

/** Header the same 403 carries (value `1`), readable before parsing the body. */
export const VERIFICATION_REQUIRED_HEADER = 'X-Email-Verification-Required';

/** Inertia component of the verification notice page. */
const NOTICE_COMPONENT = 'Auth/VerifyEmail';

export interface VerificationGate {
    /** True while the signed-in account must verify its email; false for guests and older cores. */
    blocked: ComputedRef<boolean>;
    /** Generic translated reason, the same as `reason('form')`. */
    message: ComputedRef<string>;
    /** URL of the verification notice page. */
    noticeUrl: string;
    /** Translated text of the link to the notice («Ver cómo»); render any arrow as decoration. */
    linkLabel: ComputedRef<string>;
    /** Translated sentence saying why the action is disabled («Para inscribirte, verifica tu email.»). */
    reason: (action: VerificationGateAction) => string;
    /**
     * True when a failed request was rejected because the account must verify its email. Accepts a
     * fetch() Response, its parsed JSON body, an axios-like error (`{ response: { status, data } }`)
     * and the response Inertia hands to `onHttpException` (`{ status, data }`). An Inertia form that
     * the server redirected to the notice reaches `onSuccess` with the notice page: passing that
     * page (`{ component: 'Auth/VerifyEmail', ... }`) also returns true, so the form can skip its
     * success message.
     */
    isVerificationRequired: (error: unknown) => boolean;
    /** Inertia visit to the verification notice. */
    goToNotice: () => void;
}

function isRecord(value: unknown): value is Record<string, unknown> {
    return typeof value === 'object' && value !== null;
}

function isFetchResponse(value: unknown): value is Response {
    return typeof Response !== 'undefined' && value instanceof Response;
}

function pathOf(url: string): string {
    try {
        return new URL(url, window.location.origin).pathname;
    } catch {
        return '';
    }
}

function matches(error: unknown, depth: number): boolean {
    if (depth > 3) {
        return false;
    }

    if (isFetchResponse(error)) {
        if (error.status === 403) {
            return error.headers.get(VERIFICATION_REQUIRED_HEADER) === '1';
        }

        // A fetch() without `Accept: application/json` follows the redirect to the notice
        return error.redirected && pathOf(error.url) === routes.auth.verifyEmail;
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
    if (error.error === VERIFICATION_REQUIRED_ERROR) {
        return true;
    }

    // Inertia page: an Inertia visit to a blocked route is redirected to the notice
    if (typeof error.component === 'string' && 'props' in error) {
        return error.component === NOTICE_COMPONENT;
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
 * Lets a form or button know that the signed-in account must verify its email before the
 * server accepts it (`auth.user.mustVerifyEmail`, core 3.10 or later). Guests and accounts that
 * need no verification are never blocked.
 *
 * @example
 * ```vue
 * <script setup lang="ts">
 * import { useVerificationGate } from '@/composables/useVerificationGate';
 *
 * const gate = useVerificationGate();
 *
 * async function register(): Promise<void> {
 *     if (gate.blocked.value) return;
 *     const response = await fetch(url, { method: 'POST', headers: { Accept: 'application/json' } });
 *     if (gate.isVerificationRequired(response)) {
 *         gate.goToNotice(); // state changed meanwhile (e.g. in another tab)
 *         return;
 *     }
 *     // ...
 * }
 * </script>
 *
 * <template>
 *     <button type="button" :disabled="gate.blocked.value"
 *         :aria-describedby="gate.blocked.value ? 'register-blocked' : undefined" @click="register">
 *         {{ t('register') }}
 *     </button>
 *     <p v-if="gate.blocked.value" id="register-blocked">
 *         {{ gate.reason('register') }}
 *         <Link :href="gate.noticeUrl">{{ gate.linkLabel.value }} <span aria-hidden="true">→</span></Link>
 *     </p>
 * </template>
 * ```
 */
export function useVerificationGate(): VerificationGate {
    const { t } = useI18n();
    const { mustVerifyEmail } = useAuth();

    const reason = (action: VerificationGateAction): string =>
        t(`auth.verificationGate.reasons.${action}`);

    return {
        blocked: computed<boolean>(() => mustVerifyEmail.value),
        message: computed<string>(() => reason('form')),
        noticeUrl: routes.auth.verifyEmail,
        linkLabel: computed<string>(() => t('auth.verificationGate.linkLabel')),
        reason,
        isVerificationRequired: (error: unknown): boolean => matches(error, 0),
        goToNotice: (): void => {
            router.visit(routes.auth.verifyEmail);
        },
    };
}
