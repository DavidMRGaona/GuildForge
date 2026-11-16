<script setup lang="ts">
import { Lock, ShieldCheck, ShieldOff } from '@lucide/vue';
import { computed, nextTick, ref } from 'vue';
import { Link, router, useForm } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import BaseCard from '@/components/ui/BaseCard.vue';
import BaseButton from '@/components/ui/BaseButton.vue';
import TwoFactorDialog from '@/components/profile/twoFactor/TwoFactorDialog.vue';
import { useNotifications } from '@/composables/useNotifications';
import { useVerificationGate } from '@/composables/useVerificationGate';
import { VENUE_TIMEZONE } from '@/utils/datetime';
import type { ChangePasswordFormData } from '@/types/models';
import type { SecurityProps, TwoFactorMode } from '@/types/twoFactor';

interface Props {
    security: SecurityProps;
}

const props = defineProps<Props>();

/** From this many recovery codes down, the page suggests making new ones. */
const FEW_CODES = 2;

const { t, locale } = useI18n();
const notifications = useNotifications();
// PUT /perfil/contrasena and the two-factor routes are closed to accounts that must verify their email
const gate = useVerificationGate();
const passwordBlockedId = 'password-verification-required';

// Password form
const passwordForm = useForm<ChangePasswordFormData>({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const changePassword = () => {
    if (gate.blocked.value) {
        return;
    }

    passwordForm.put('/perfil/contrasena', {
        preserveScroll: true,
        onSuccess: (page) => {
            // Must verify after all (e.g. changed in another tab): the server sent us to the notice
            if (gate.isVerificationRequired(page)) {
                return;
            }
            passwordForm.reset();
            notifications.success(t('auth.profile.passwordUpdated'));
        },
        onError: () => notifications.error(t('auth.profile.passwordError')),
    });
};

// Two-factor authentication
const twoFactor = computed(() => props.security.twoFactor);
const dialogOpen = ref(false);
const statusRef = ref<HTMLElement | null>(null);
const dialogMode = ref<TwoFactorMode>('enable');

const sinceLabel = computed(() =>
    twoFactor.value.since === null
        ? ''
        : new Date(twoFactor.value.since).toLocaleDateString(locale.value, {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
              timeZone: VENUE_TIMEZONE,
          })
);
const fewCodesLeft = computed(() => twoFactor.value.remainingCodes <= FEW_CODES);

function openDialog(mode: TwoFactorMode): void {
    if (gate.blocked.value) {
        return;
    }

    dialogMode.value = mode;
    dialogOpen.value = true;
}

function onDone(mode: TwoFactorMode): void {
    notifications.success(t(`auth.profile.twoFactor.done.${mode}`));
    // The answer carries no state: the page asks for the numbers again
    router.reload({
        only: ['security'],
        onFinish: () => {
            // Turning it on or off replaces the button that opened the dialog: focus would fall on
            // the page, so it goes to the status of the card (the other modes keep their button)
            if (mode === 'enable' || mode === 'disable') {
                void nextTick(() => statusRef.value?.focus());
            }
        },
    });
}
</script>

<template>
    <div class="space-y-6">
        <div class="grid items-start gap-6 lg:grid-cols-2">
            <!-- Two-factor authentication -->
            <BaseCard>
                <template #header>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100 text-primary dark:bg-primary-900/30"
                        >
                            <component
                                :is="twoFactor.active ? ShieldCheck : ShieldOff"
                                class="h-5 w-5"
                                aria-hidden="true"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h3 class="text-lg font-semibold text-base-primary">
                                {{ t('auth.profile.twoFactor.title') }}
                            </h3>
                        </div>
                    </div>
                </template>

                <div class="space-y-4" data-testid="two-factor-card">
                    <p class="text-sm text-base-secondary">
                        {{ t('auth.profile.twoFactor.intro') }}
                    </p>

                    <p
                        v-if="gate.blocked.value"
                        id="two-factor-verification-required"
                        class="rounded-md bg-warning-light p-3 text-sm text-base-primary"
                    >
                        {{ gate.reason('form') }}
                        <Link
                            :href="gate.noticeUrl"
                            class="font-semibold underline underline-offset-2 hover:no-underline"
                        >
                            {{ gate.linkLabel.value }}
                            <span aria-hidden="true">→</span>
                        </Link>
                    </p>

                    <!-- Off -->
                    <template v-if="!twoFactor.active">
                        <p
                            ref="statusRef"
                            tabindex="-1"
                            class="text-sm font-medium text-base-primary focus:outline-none"
                            data-testid="two-factor-status"
                        >
                            {{ t('auth.profile.twoFactor.off.status') }}
                        </p>
                        <div class="flex justify-end">
                            <BaseButton
                                type="button"
                                :disabled="gate.blocked.value"
                                :aria-describedby="
                                    gate.blocked.value
                                        ? 'two-factor-verification-required'
                                        : undefined
                                "
                                @click="openDialog('enable')"
                            >
                                {{ t('auth.profile.twoFactor.off.enable') }}
                            </BaseButton>
                        </div>
                    </template>

                    <!-- On -->
                    <template v-else>
                        <div class="space-y-1 text-sm">
                            <p
                                ref="statusRef"
                                tabindex="-1"
                                class="font-medium text-success focus:outline-none"
                                data-testid="two-factor-status"
                            >
                                {{ t('auth.profile.twoFactor.on.status') }}
                            </p>
                            <p class="text-base-secondary">
                                {{ t('auth.profile.twoFactor.on.since', { date: sinceLabel }) }}
                            </p>
                            <p class="text-base-secondary" data-testid="two-factor-codes-left">
                                {{
                                    t(
                                        'auth.profile.twoFactor.on.codesLeft',
                                        { count: twoFactor.remainingCodes },
                                        twoFactor.remainingCodes
                                    )
                                }}
                            </p>
                        </div>

                        <p
                            v-if="fewCodesLeft"
                            role="status"
                            class="rounded-md bg-warning-light p-3 text-sm text-base-primary"
                            data-testid="two-factor-few-codes"
                        >
                            {{ t('auth.profile.twoFactor.on.fewCodes') }}
                        </p>

                        <div class="flex flex-wrap justify-end gap-2">
                            <BaseButton
                                type="button"
                                variant="secondary"
                                size="sm"
                                :disabled="gate.blocked.value"
                                @click="openDialog('recoveryCodes')"
                            >
                                {{ t('auth.profile.twoFactor.on.regenerate') }}
                            </BaseButton>
                            <BaseButton
                                type="button"
                                variant="secondary"
                                size="sm"
                                :disabled="gate.blocked.value"
                                @click="openDialog('changeApp')"
                            >
                                {{ t('auth.profile.twoFactor.on.changeApp') }}
                            </BaseButton>
                            <BaseButton
                                v-if="twoFactor.canDisable"
                                type="button"
                                variant="danger"
                                size="sm"
                                :disabled="gate.blocked.value"
                                @click="openDialog('disable')"
                            >
                                {{ t('auth.profile.twoFactor.on.disable') }}
                            </BaseButton>
                        </div>
                        <p
                            v-if="!twoFactor.canDisable"
                            class="text-sm text-base-muted"
                            data-testid="two-factor-cannot-disable"
                        >
                            {{ t('auth.profile.twoFactor.on.cannotDisable') }}
                        </p>
                    </template>
                </div>
            </BaseCard>

            <!-- Password -->
            <BaseCard>
                <template #header>
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-10 w-10 items-center justify-center rounded-lg bg-primary-100 text-primary dark:bg-primary-900/30"
                        >
                            <Lock class="h-5 w-5" aria-hidden="true" />
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-base-primary">
                                {{ t('auth.profile.password') }}
                            </h3>
                        </div>
                    </div>
                </template>

                <form class="space-y-4" @submit.prevent="changePassword">
                    <p
                        v-if="gate.blocked.value"
                        :id="passwordBlockedId"
                        class="rounded-md bg-warning-light p-3 text-sm text-base-primary"
                    >
                        {{ gate.reason('changePassword') }}
                        <Link
                            :href="gate.noticeUrl"
                            class="font-semibold underline underline-offset-2 hover:no-underline"
                        >
                            {{ gate.linkLabel.value }}
                            <span aria-hidden="true">→</span>
                        </Link>
                    </p>

                    <fieldset
                        class="min-w-0 space-y-4"
                        :disabled="gate.blocked.value"
                        :aria-describedby="gate.blocked.value ? passwordBlockedId : undefined"
                    >
                        <div>
                            <label
                                for="current_password"
                                class="block text-sm font-medium text-base-secondary"
                            >
                                {{ t('auth.profile.currentPassword') }}
                            </label>
                            <input
                                id="current_password"
                                v-model="passwordForm.current_password"
                                type="password"
                                required
                                autocomplete="current-password"
                                class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary focus:border-primary-500 focus:outline-none focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            />
                            <p
                                v-if="passwordForm.errors.current_password"
                                class="mt-1 text-sm text-error"
                            >
                                {{ passwordForm.errors.current_password }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="new_password"
                                class="block text-sm font-medium text-base-secondary"
                            >
                                {{ t('auth.profile.newPassword') }}
                            </label>
                            <input
                                id="new_password"
                                v-model="passwordForm.password"
                                type="password"
                                required
                                autocomplete="new-password"
                                class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary focus:border-primary-500 focus:outline-none focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            />
                            <p v-if="passwordForm.errors.password" class="mt-1 text-sm text-error">
                                {{ passwordForm.errors.password }}
                            </p>
                        </div>

                        <div>
                            <label
                                for="password_confirmation"
                                class="block text-sm font-medium text-base-secondary"
                            >
                                {{ t('auth.profile.confirmPassword') }}
                            </label>
                            <input
                                id="password_confirmation"
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                required
                                autocomplete="new-password"
                                class="mt-1 block w-full rounded-md border border-default bg-surface px-3 py-2 text-base-primary focus:border-primary-500 focus:outline-none focus:ring-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                            />
                        </div>

                        <div class="flex justify-end pt-2">
                            <BaseButton
                                type="submit"
                                variant="primary"
                                :disabled="passwordForm.processing || gate.blocked.value"
                                :aria-describedby="
                                    gate.blocked.value ? passwordBlockedId : undefined
                                "
                            >
                                {{
                                    passwordForm.processing
                                        ? t('common.loading')
                                        : t('auth.profile.updatePassword')
                                }}
                            </BaseButton>
                        </div>
                    </fieldset>
                </form>
            </BaseCard>
        </div>

        <TwoFactorDialog v-model:open="dialogOpen" :mode="dialogMode" @done="onDone" />
    </div>
</template>
