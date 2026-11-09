<script setup lang="ts">
import { computed } from 'vue';
import type { MailPreference } from '@/types/mailPreferences';

/**
 * One optional category: its text on the left and a control on the right. The control is a
 * switch unless the parent fills the `control` slot with another one (for instance a choice
 * of how the mail arrives), which gets the ids it must be labelled and described by.
 * Mandatory categories are not rows: `MailMandatoryList` lists them without a control.
 */
interface Props {
    category: MailPreference;
    /** Current choice, which may differ from the saved one until the person saves. */
    modelValue: boolean;
    /** Locks the control for now (saving, or an account that must verify its email). */
    busy?: boolean;
    /** Translated error for this category, shown under it. */
    error?: string;
}

const props = withDefaults(defineProps<Props>(), { busy: false, error: '' });

const emit = defineEmits<{ 'update:modelValue': [value: boolean] }>();

// The id of the category key (`events.reminders`) is safe in an attribute and unique on the page
const baseId = computed(() => `mail-pref-${props.category.key.replace(/[^a-z0-9-]/gi, '-')}`);
const labelId = computed(() => `${baseId.value}-label`);
const descriptionId = computed(() => `${baseId.value}-description`);
const errorId = computed(() => `${baseId.value}-error`);

// aria-disabled (not `disabled`) keeps a locked switch in the tab order, so a screen reader
// user reaches it and hears that it cannot be changed right now
const locked = computed(() => props.busy);
const checked = computed(() => props.modelValue);

const describedBy = computed(() =>
    [descriptionId.value, props.error ? errorId.value : null]
        .filter((id): id is string => id !== null)
        .join(' ')
);

function toggle(): void {
    if (locked.value) {
        return;
    }

    emit('update:modelValue', !props.modelValue);
}
</script>

<template>
    <li
        class="flex flex-wrap items-start justify-between gap-x-4 gap-y-3 py-4"
        :data-category="category.key"
    >
        <!-- The text takes the room left; a control wider than the switch wraps under it on narrow screens -->
        <div class="min-w-40 flex-1 basis-0">
            <p :id="labelId" class="text-sm font-medium text-base-primary">
                {{ category.label }}
            </p>
            <p :id="descriptionId" class="mt-0.5 text-sm text-base-secondary">
                {{ category.description }}
            </p>
            <p v-if="error" :id="errorId" class="mt-1.5 text-sm text-error">{{ error }}</p>
        </div>

        <slot name="control" :label-id="labelId" :described-by="describedBy" :locked="locked">
            <!-- 44 px tall hit area around the 24 px track -->
            <button
                type="button"
                role="switch"
                :aria-checked="checked"
                :aria-disabled="locked"
                :aria-labelledby="labelId"
                :aria-describedby="describedBy"
                class="-my-2 flex min-h-[44px] min-w-[44px] shrink-0 items-center justify-center rounded-md focus:outline-none focus-visible:ring-2 focus-visible:ring-primary-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface"
                :class="locked ? 'cursor-not-allowed' : 'cursor-pointer'"
                @click="toggle"
            >
                <span
                    class="relative block h-6 w-11 rounded-full border transition-colors duration-200"
                    :class="[
                        checked
                            ? 'border-primary-600 bg-primary-600 dark:border-primary-500 dark:bg-primary-500'
                            : 'border-neutral-300 bg-neutral-200 dark:border-neutral-600 dark:bg-neutral-700',
                        locked ? 'opacity-60' : '',
                    ]"
                    aria-hidden="true"
                >
                    <!-- Offsets count from inside the 1 px border (22 x 42 px): 1 px each side keeps it centred -->
                    <span
                        class="absolute left-px top-px h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200 ease-in-out"
                        :class="checked ? 'translate-x-5' : 'translate-x-0'"
                    />
                </span>
            </button>
        </slot>
    </li>
</template>
