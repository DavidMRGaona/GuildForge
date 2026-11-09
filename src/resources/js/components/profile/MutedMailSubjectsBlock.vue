<script setup lang="ts">
import { BellOff } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import { useI18n } from 'vue-i18n';
import BaseButton from '@/components/ui/BaseButton.vue';
import type { MutedMailSubject } from '@/types/mailPreferences';

interface Props {
    subjects: MutedMailSubject[];
    /** The account must verify its email first: the endpoint is closed to it. */
    blocked?: boolean;
}

const props = withDefaults(defineProps<Props>(), { blocked: false });

const ENDPOINT = '/perfil/correos/silenciados';

const { t, locale } = useI18n();

/** Key of the row being unmuted, so only its button shows the spinner. */
const pending = ref<string | null>(null);
const failed = ref(false);

const keyOf = (subject: MutedMailSubject): string => `${subject.type}:${subject.id}`;

const dateFormat = computed(
    () =>
        new Intl.DateTimeFormat(locale.value, {
            day: 'numeric',
            month: 'long',
            year: 'numeric',
            timeZone: 'UTC',
        })
);

/** «1 de diciembre de 2026», from the display-zone day the instant carries. */
function mutedOn(subject: MutedMailSubject): string | null {
    // The ISO string is already in the display zone: its first ten characters are that day.
    // Read and format it in UTC so the browser's own zone cannot move it a day
    const day = subject.mutedAt?.slice(0, 10);
    if (day === undefined || !/^\d{4}-\d{2}-\d{2}$/.test(day)) {
        return null;
    }

    return dateFormat.value.format(new Date(`${day}T00:00:00Z`));
}

function unmute(subject: MutedMailSubject): void {
    if (pending.value !== null || props.blocked) {
        return;
    }

    failed.value = false;

    router.delete(ENDPOINT, {
        data: { type: subject.type, id: subject.id },
        preserveScroll: true,
        preserveState: true,
        onStart: () => {
            pending.value = keyOf(subject);
        },
        onFinish: () => {
            pending.value = null;
        },
        onError: () => {
            failed.value = true;
        },
    });
}
</script>

<template>
    <section aria-labelledby="muted-mail-subjects-title" data-testid="muted-mail-subjects">
        <div class="flex items-center gap-2">
            <BellOff class="h-5 w-5 shrink-0 text-base-secondary" aria-hidden="true" />
            <h3 id="muted-mail-subjects-title" class="text-base font-semibold text-base-primary">
                {{ t('auth.profile.mail.muted.title') }}
            </h3>
        </div>
        <p class="mt-1 text-sm text-base-secondary">
            {{ t('auth.profile.mail.muted.description') }}
        </p>

        <p
            v-if="subjects.length === 0"
            class="mt-4 rounded-lg border border-dashed border-default p-4 text-sm text-base-secondary"
            data-testid="muted-mail-subjects-empty"
        >
            {{ t('auth.profile.mail.muted.empty') }}
        </p>

        <ul v-else class="mt-4 rounded-lg border border-default">
            <li
                v-for="subject in subjects"
                :key="keyOf(subject)"
                class="flex flex-col gap-3 border-default p-4 not-first:border-t sm:flex-row sm:items-center sm:justify-between"
                :data-subject="keyOf(subject)"
            >
                <div class="min-w-0">
                    <p
                        v-if="subject.typeLabel"
                        class="text-xs font-medium uppercase tracking-wide text-base-secondary"
                    >
                        {{ subject.typeLabel }}
                    </p>
                    <p class="break-words text-sm font-medium text-base-primary">
                        {{ subject.label }}
                    </p>
                    <p v-if="mutedOn(subject)" class="mt-0.5 text-xs text-base-secondary">
                        {{ t('auth.profile.mail.muted.since', { date: mutedOn(subject) }) }}
                    </p>
                </div>
                <BaseButton
                    type="button"
                    variant="secondary"
                    size="sm"
                    class="w-full shrink-0 sm:w-auto"
                    :loading="pending === keyOf(subject)"
                    :disabled="blocked || (pending !== null && pending !== keyOf(subject))"
                    :aria-label="
                        t('auth.profile.mail.muted.unmuteLabel', { subject: subject.label })
                    "
                    @click="unmute(subject)"
                >
                    {{ t('auth.profile.mail.muted.unmute') }}
                </BaseButton>
            </li>
        </ul>

        <p v-if="failed" role="alert" class="mt-3 text-sm text-error">
            {{ t('auth.profile.mail.muted.error') }}
        </p>
    </section>
</template>
