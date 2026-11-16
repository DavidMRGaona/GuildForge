<script setup lang="ts">
import { computed, nextTick, ref } from 'vue';
import { useForm, Link, setLayoutProps } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import AuthLayout from '@/layouts/AuthLayout.vue';
import type { AuthLayoutProps } from '@/types/layouts';
import BaseButton from '@/components/ui/BaseButton.vue';
import HelpLink from '@/components/knowledge/HelpLink.vue';
import { useRoutes } from '@/composables/useRoutes';
import type { TwoFactorChallengeFormData } from '@/types/models';

/**
 * The second step of signing in for an account with two-factor authentication on (core#770): the
 * password was right, and the server signs in only once this code is accepted.
 */
const { t } = useI18n();

defineOptions({ layout: AuthLayout });
setLayoutProps<AuthLayoutProps>({
    title: t('auth.twoFactorChallenge.title'),
});

const routes = useRoutes();
const codeInput = ref<HTMLInputElement | null>(null);

const form = useForm<TwoFactorChallengeFormData>({
    code: '',
    recovery: false,
});

const description = computed(() =>
    form.recovery
        ? t('auth.twoFactorChallenge.subtitleRecovery')
        : t('auth.twoFactorChallenge.subtitleApp')
);

const toggleMode = async (): Promise<void> => {
    form.recovery = !form.recovery;
    form.code = '';
    form.clearErrors();
    await nextTick();
    codeInput.value?.focus();
};

const submit = (): void => {
    form.post(routes.auth.twoFactorChallenge, {
        onFinish: () => form.reset('code'),
    });
};
</script>

<template>
    <form class="space-y-6" @submit.prevent="submit">
        <div class="flex items-start justify-between gap-3">
            <p id="two-factor-description" class="text-sm text-base-secondary">
                {{ description }}
            </p>
            <HelpLink guide="core.site.two-factor" />
        </div>

        <div>
            <label for="code" class="block text-sm font-medium text-base-secondary">
                {{
                    form.recovery
                        ? t('auth.twoFactorChallenge.recoveryCode')
                        : t('auth.twoFactorChallenge.appCode')
                }}
            </label>
            <input
                id="code"
                ref="codeInput"
                v-model="form.code"
                type="text"
                required
                autofocus
                :inputmode="form.recovery ? 'text' : 'numeric'"
                :autocomplete="form.recovery ? 'off' : 'one-time-code'"
                autocapitalize="none"
                spellcheck="false"
                :maxlength="64"
                aria-describedby="two-factor-description"
                :aria-invalid="form.errors.code ? 'true' : undefined"
                class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary placeholder-neutral-400 dark:placeholder-neutral-500 focus:border-primary-500 focus:outline-none focus:ring-primary-500"
            />
            <p v-if="form.errors.code" class="mt-1 text-sm text-error" role="alert">
                {{ form.errors.code }}
            </p>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <button
                type="button"
                class="text-sm font-medium text-primary hover:opacity-80"
                @click="toggleMode"
            >
                {{
                    form.recovery
                        ? t('auth.twoFactorChallenge.useAppCode')
                        : t('auth.twoFactorChallenge.useRecoveryCode')
                }}
            </button>

            <Link
                :href="routes.auth.login"
                class="text-sm font-medium text-primary hover:opacity-80"
            >
                {{ t('auth.twoFactorChallenge.back') }}
            </Link>
        </div>

        <div>
            <BaseButton type="submit" variant="primary" class="w-full" :disabled="form.processing">
                {{ form.processing ? t('common.loading') : t('auth.twoFactorChallenge.submit') }}
            </BaseButton>
        </div>
    </form>
</template>
