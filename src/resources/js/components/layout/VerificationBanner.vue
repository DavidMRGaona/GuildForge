<script setup lang="ts">
import { computed, ref } from 'vue';
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { MailWarning } from '@lucide/vue';
import { useAuth } from '@/composables/useAuth';
import { useRoutes } from '@/composables/useRoutes';

type ResendState = 'idle' | 'sent' | 'throttled' | 'error';

const HTTP_TOO_MANY_REQUESTS = 429;

const { t } = useI18n();
const page = usePage();
const routes = useRoutes();
const { user, mustVerifyEmail } = useAuth();

// The notice page explains the same thing (and uses AuthLayout anyway)
const visible = computed<boolean>(
    () => mustVerifyEmail.value && page.component !== 'Auth/VerifyEmail'
);

const email = computed<string>(() => user.value?.verificationEmail ?? user.value?.email ?? '');

const form = useForm({});
const state = ref<ResendState>('idle');

const feedback = computed<string>(() => {
    switch (state.value) {
        case 'sent':
            return t('auth.verificationBanner.sent');
        case 'throttled':
            return t('auth.verificationBanner.throttled');
        case 'error':
            return t('auth.verificationBanner.error');
        default:
            return '';
    }
});

function resend(): void {
    state.value = 'idle';

    form.post(routes.auth.resendVerification, {
        preserveScroll: true,
        preserveState: true,
        // Partial reload: the page does not get the resend flash (the banner shows its own
        // feedback); the reload consumes it. Leaving `auth` keeps mustVerifyEmail current.
        only: ['auth'],
        onSuccess: () => {
            state.value = 'sent';
        },
        onError: () => {
            state.value = 'error';
        },
        // Handled here instead of Inertia's error dialog
        onHttpException: (response) => {
            state.value = response.status === HTTP_TOO_MANY_REQUESTS ? 'throttled' : 'error';

            return false;
        },
        onNetworkError: () => {
            state.value = 'error';

            return false;
        },
    });
}
</script>

<template>
    <div
        v-if="visible"
        class="border-b border-warning bg-warning-light"
        :aria-label="t('auth.verificationBanner.label')"
        role="region"
    >
        <div
            class="mx-auto flex max-w-7xl flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-4 sm:px-6 lg:px-8"
        >
            <div class="flex items-start gap-3" role="status">
                <MailWarning class="mt-0.5 h-5 w-5 shrink-0 text-warning" aria-hidden="true" />
                <div class="text-sm text-base-primary">
                    <p>{{ t('auth.verificationBanner.message', { email }) }}</p>
                    <p v-if="feedback" class="mt-1 font-medium">{{ feedback }}</p>
                </div>
            </div>

            <div class="flex shrink-0 flex-wrap items-center gap-x-4 gap-y-1 pl-8 sm:pl-0">
                <button
                    type="button"
                    class="min-h-[44px] text-sm font-semibold text-base-primary underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 disabled:cursor-wait disabled:opacity-70"
                    :disabled="form.processing"
                    @click="resend"
                >
                    {{
                        form.processing
                            ? t('auth.verificationBanner.sending')
                            : t('auth.verificationBanner.resend')
                    }}
                </button>
                <Link
                    :href="routes.auth.verifyEmail"
                    class="inline-flex min-h-[44px] items-center text-sm font-semibold text-base-primary underline underline-offset-2 hover:no-underline focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500"
                >
                    {{ t('auth.verificationBanner.moreInfo') }}
                </Link>
            </div>
        </div>
    </div>
</template>
