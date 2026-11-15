<script setup lang="ts">
import { computed, toRef, useId } from 'vue';
import { useI18n } from 'vue-i18n';
import { CircleAlert, CircleCheck, LoaderCircle } from '@lucide/vue';
import { useHandleAvailability } from '@/composables/useHandleAvailability';
import type { ProfileEditor } from '@/types/profileEditor';

/**
 * «Dirección»: the address after `/miembros/`. While typing, a moment after the last key, it says
 * whether the address can be chosen; an address somebody else holds or held and a reserved word both
 * read «No disponible». It can be chosen once every 30 days: until then it is read-only, with the date.
 */
const props = defineProps<{
    editor: ProfileEditor;
    error?: string | undefined;
}>();

const model = defineModel<string>({ required: true });

const { t, locale } = useI18n();
const uid = useId();

const lockedUntil = computed(() => {
    const iso = props.editor.nextHandleChangeAt;
    const match = iso ? /^(\d{4})-(\d{2})-(\d{2})/.exec(iso) : null;

    // The day of the display zone, read from the text itself, as the server sent it
    return match
        ? new Date(
              Date.UTC(Number(match[1]), Number(match[2]) - 1, Number(match[3]))
          ).toLocaleDateString(locale.value, {
              day: 'numeric',
              month: 'long',
              year: 'numeric',
              timeZone: 'UTC',
          })
        : null;
});

const { status } = useHandleAvailability(toRef(model), {
    url: props.editor.urls.availability,
    current: props.editor.handle,
    limits: { min: props.editor.limits.handleMin, max: props.editor.limits.handleMax },
    enabled: () => lockedUntil.value === null,
});

const statusText = computed(() => {
    switch (status.value) {
        case 'checking':
            return t('publicProfile.editor.handle.checking');
        case 'available':
            return t('publicProfile.editor.handle.available');
        case 'current':
            return t('publicProfile.editor.handle.current');
        case 'unavailable':
            return t('publicProfile.editor.handle.unavailable');
        case 'malformed':
            return t('publicProfile.editor.handle.malformed', {
                min: props.editor.limits.handleMin,
                max: props.editor.limits.handleMax,
            });
        case 'failed':
            return t('publicProfile.editor.handle.failed');
        default:
            return '';
    }
});

const isProblem = computed(() => status.value === 'unavailable' || status.value === 'malformed');
const describedBy = computed(() =>
    [
        props.error ? `${uid}-error` : null,
        lockedUntil.value === null ? `${uid}-status` : null,
        `${uid}-help`,
    ]
        .filter(Boolean)
        .join(' ')
);
</script>

<template>
    <div>
        <label :for="`${uid}-input`" class="block text-sm font-semibold text-base-primary">
            {{ t('publicProfile.editor.handle.label') }}
        </label>
        <div
            class="mt-2 flex items-stretch overflow-hidden rounded-md border bg-surface focus-within:ring-2 focus-within:ring-primary-500"
            :class="error || isProblem ? 'border-red-500 dark:border-red-400' : 'border-default'"
        >
            <span
                class="flex items-center border-r border-default bg-muted px-3 text-sm text-base-muted select-none"
                aria-hidden="true"
            >
                /miembros/
            </span>
            <input
                :id="`${uid}-input`"
                v-model="model"
                type="text"
                name="handle"
                autocomplete="off"
                autocapitalize="none"
                spellcheck="false"
                :maxlength="editor.limits.handleMax + 10"
                :readonly="lockedUntil !== null"
                :aria-invalid="error || isProblem ? true : undefined"
                :aria-describedby="describedBy"
                class="min-w-0 flex-1 bg-transparent px-3 py-2 text-sm text-base-primary focus:outline-none read-only:cursor-not-allowed read-only:text-base-muted"
                data-testid="handle-input"
            />
        </div>
        <p v-if="error" :id="`${uid}-error`" class="mt-2 text-sm text-red-600 dark:text-red-400">
            {{ error }}
        </p>
        <!-- Polite: said once the person stops typing, never over every key -->
        <p
            v-if="lockedUntil === null"
            :id="`${uid}-status`"
            class="mt-2 flex min-h-5 items-center gap-1.5 text-sm"
            :class="isProblem ? 'text-red-600 dark:text-red-400' : 'text-base-muted'"
            aria-live="polite"
            data-testid="handle-status"
        >
            <LoaderCircle
                v-if="status === 'checking'"
                class="h-4 w-4 animate-spin motion-reduce:animate-none"
                aria-hidden="true"
            />
            <CircleCheck
                v-else-if="status === 'available' || status === 'current'"
                class="h-4 w-4 text-green-600 dark:text-green-400"
                aria-hidden="true"
            />
            <CircleAlert v-else-if="isProblem" class="h-4 w-4" aria-hidden="true" />
            <span>{{ statusText }}</span>
        </p>
        <p :id="`${uid}-help`" class="mt-1 text-xs text-base-muted">
            <template v-if="lockedUntil">
                {{ t('publicProfile.editor.handle.locked', { date: lockedUntil }) }}
            </template>
            <template v-else>
                {{
                    t('publicProfile.editor.handle.help', { days: editor.limits.handleChangeDays })
                }}
            </template>
        </p>
    </div>
</template>
