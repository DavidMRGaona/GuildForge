<script setup lang="ts">
import { PauseCircle } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import type { MailPause } from '@/types/mailPreferences';

interface Props {
    pause: MailPause;
    /** The account must verify its email first: the endpoints are closed to it. */
    blocked?: boolean;
}

const props = withDefaults(defineProps<Props>(), { blocked: false });

const ENDPOINT = '/perfil/correos/pausa';
const DURATIONS = ['week', 'month', 'indefinite'] as const;
type Duration = (typeof DURATIONS)[number];

const { t, locale } = useI18n();

const duration = ref<Duration>('week');
const processing = ref(false);
const failed = ref(false);

const locked = computed(() => processing.value || props.blocked);

/** «12 de diciembre» (with the year only when it is not this one), from the display-zone day. */
const lastDay = computed<string | null>(() => {
    if (props.pause.lastDay === null) {
        return null;
    }

    // The string is a calendar day, not an instant: read and format it in UTC so the
    // browser's own zone cannot move it a day
    const day = new Date(`${props.pause.lastDay}T00:00:00Z`);
    const sameYear = day.getUTCFullYear() === new Date().getFullYear();

    return new Intl.DateTimeFormat(locale.value, {
        day: 'numeric',
        month: 'long',
        year: sameYear ? undefined : 'numeric',
        timeZone: 'UTC',
    }).format(day);
});

const statusText = computed(() =>
    lastDay.value === null
        ? t('auth.profile.mail.pause.activeIndefinite')
        : t('auth.profile.mail.pause.activeUntil', { date: lastDay.value })
);

function send(method: 'put' | 'delete'): void {
    if (locked.value) {
        return;
    }

    failed.value = false;

    const options = {
        preserveScroll: true,
        preserveState: true,
        onStart: (): void => {
            processing.value = true;
        },
        onFinish: (): void => {
            processing.value = false;
        },
        onError: (): void => {
            failed.value = true;
        },
    };

    if (method === 'put') {
        router.put(ENDPOINT, { duration: duration.value }, options);
    } else {
        router.delete(ENDPOINT, options);
    }
}
</script>

<template>
    <div
        v-if="pause.active"
        class="rounded-lg border border-primary-200 bg-primary-50 p-4 dark:border-primary-800 dark:bg-primary-900/20"
        data-testid="mail-pause-active"
    >
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex min-w-0 items-start gap-3">
                <PauseCircle
                    class="mt-0.5 h-5 w-5 shrink-0 text-primary dark:text-primary-300"
                    aria-hidden="true"
                />
                <div class="min-w-0">
                    <p role="status" class="text-sm font-medium text-base-primary">
                        {{ statusText }}
                    </p>
                    <p class="mt-0.5 text-sm text-base-secondary">
                        {{ t('auth.profile.mail.pause.activeHint') }}
                    </p>
                </div>
            </div>
            <BaseButton
                type="button"
                variant="secondary"
                class="shrink-0"
                :loading="processing"
                :disabled="blocked"
                @click="send('delete')"
            >
                {{ t('auth.profile.mail.pause.resume') }}
            </BaseButton>
        </div>
        <p v-if="failed" role="alert" class="mt-3 text-sm text-error">
            {{ t('auth.profile.mail.pause.error') }}
        </p>
    </div>

    <fieldset
        v-else
        class="rounded-lg border border-default p-4"
        aria-describedby="mail-pause-description"
        data-testid="mail-pause-form"
    >
        <legend class="px-1 text-sm font-semibold text-base-primary">
            {{ t('auth.profile.mail.pause.title') }}
        </legend>
        <p id="mail-pause-description" class="text-sm text-base-secondary">
            {{ t('auth.profile.mail.pause.description') }}
        </p>

        <div class="mt-3 flex flex-col gap-1 sm:flex-row sm:flex-wrap sm:gap-x-6">
            <label
                v-for="option in DURATIONS"
                :key="option"
                class="flex min-h-[44px] cursor-pointer items-center gap-2 text-sm text-base-primary"
            >
                <input
                    v-model="duration"
                    type="radio"
                    name="mail-pause-duration"
                    :value="option"
                    :disabled="locked"
                    class="h-4 w-4 border-neutral-300 text-primary-600 focus:ring-2 focus:ring-primary-500 dark:border-neutral-600"
                />
                {{ t(`auth.profile.mail.pause.durations.${option}`) }}
            </label>
        </div>

        <div class="mt-3">
            <BaseButton
                type="button"
                variant="secondary"
                :loading="processing"
                :disabled="blocked"
                @click="send('put')"
            >
                {{ t('auth.profile.mail.pause.start') }}
            </BaseButton>
        </div>
        <p v-if="failed" role="alert" class="mt-3 text-sm text-error">
            {{ t('auth.profile.mail.pause.error') }}
        </p>
    </fieldset>
</template>
