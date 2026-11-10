<script setup lang="ts">
import { computed } from 'vue';
import PreferenceSwitch from '@/components/profile/PreferenceSwitch.vue';
import type { MailDelivery, MailPreference } from '@/types/mailPreferences';

/**
 * One optional category: its text on the left and a control on the right. The control is a
 * switch unless the parent fills the `control` slot with another one (for instance a choice
 * of how the mail arrives, `MailDeliveryChoice`), which gets the ids it must be labelled and
 * described by. The switch turns the category on (at once) or off.
 * Mandatory categories are not rows: `MailMandatoryList` lists them without a control.
 */
interface Props {
    category: MailPreference;
    /** Current choice, which may differ from the saved one until the person saves. */
    modelValue: MailDelivery;
    /** Locks the control for now (saving, or an account that must verify its email). */
    busy?: boolean;
    /** Translated error for this category, shown under it. */
    error?: string;
}

const props = withDefaults(defineProps<Props>(), { busy: false, error: '' });

const emit = defineEmits<{ 'update:modelValue': [value: MailDelivery] }>();

// The id of the category key (`events.reminders`) is safe in an attribute and unique on the page
const baseId = computed(() => `mail-pref-${props.category.key.replace(/[^a-z0-9-]/gi, '-')}`);
const labelId = computed(() => `${baseId.value}-label`);
const descriptionId = computed(() => `${baseId.value}-description`);
const errorId = computed(() => `${baseId.value}-error`);

const locked = computed(() => props.busy);
const checked = computed(() => props.modelValue !== 'none');

const describedBy = computed(() =>
    [descriptionId.value, props.error ? errorId.value : null]
        .filter((id): id is string => id !== null)
        .join(' ')
);

function toggle(): void {
    if (locked.value) {
        return;
    }

    emit('update:modelValue', checked.value ? 'none' : 'immediate');
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

        <slot
            name="control"
            :base-id="baseId"
            :label-id="labelId"
            :described-by="describedBy"
            :locked="locked"
        >
            <PreferenceSwitch
                :checked="checked"
                :locked="locked"
                :labelled-by="labelId"
                :described-by="describedBy"
                @toggle="toggle"
            />
        </slot>
    </li>
</template>
