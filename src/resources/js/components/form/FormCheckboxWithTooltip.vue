<script setup lang="ts">
import { Check, CircleQuestionMark } from '@lucide/vue';
import { useI18n } from 'vue-i18n';
import FormTooltip from './FormTooltip.vue';

const props = withDefaults(
    defineProps<{
        modelValue: string[];
        value: string;
        label: string;
        description?: string;
        id?: string;
    }>(),
    {
        description: '',
        id: '',
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: string[]];
}>();

const { t } = useI18n();

function isChecked(): boolean {
    return props.modelValue.includes(props.value);
}

function toggle(): void {
    const newValue = isChecked()
        ? props.modelValue.filter((v) => v !== props.value)
        : [...props.modelValue, props.value];
    emit('update:modelValue', newValue);
}
</script>

<template>
    <label class="inline-flex items-start gap-3 cursor-pointer group">
        <!-- Hidden input for accessibility -->
        <input
            :id="id"
            type="checkbox"
            :checked="isChecked()"
            class="peer sr-only"
            @change="toggle"
        />

        <!-- Custom checkbox visual -->
        <div
            class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-2 flex items-center justify-center transition-all duration-150 peer-focus:ring-2 peer-focus:ring-primary-500 peer-focus:ring-offset-2"
            :class="[
                isChecked()
                    ? 'bg-primary-600 border-primary-600 dark:bg-primary-500 dark:border-primary-500 group-hover:bg-primary-700 dark:group-hover:bg-primary-400'
                    : 'bg-surface border-neutral-300 dark:border-neutral-600 group-hover:border-primary-400 dark:group-hover:border-primary-500 group-hover:shadow-sm',
            ]"
        >
            <!-- Checkmark with animation -->
            <Check
                class="h-3 w-3 transition-all duration-150 text-white"
                :class="isChecked() ? 'opacity-100 scale-100' : 'opacity-0 scale-0'"
                :stroke-width="3"
                aria-hidden="true"
            />
        </div>

        <div class="flex items-center gap-1.5 flex-1 min-w-0">
            <span class="text-sm text-base-primary select-none">
                {{ label }}
            </span>
            <FormTooltip v-if="description" :content="description" position="top">
                <button
                    type="button"
                    class="inline-flex items-center justify-center h-4 w-4 rounded-full bg-neutral-200 dark:bg-neutral-700 text-base-muted hover:text-base-secondary transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1"
                    :aria-label="t('common.moreInfo')"
                    @click.prevent
                >
                    <CircleQuestionMark class="h-3 w-3" aria-hidden="true" />
                </button>
            </FormTooltip>
        </div>
    </label>
</template>
