<script setup lang="ts">
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
    <div class="flex items-start gap-3">
        <input
            :id="id"
            type="checkbox"
            :checked="isChecked()"
            @change="toggle"
            class="mt-0.5 h-4 w-4 rounded border-default
                   text-primary-600 focus:ring-2 focus:ring-primary-500
                   focus:ring-offset-0 cursor-pointer"
        />
        <div class="flex items-center gap-1.5 flex-1 min-w-0">
            <label
                :for="id"
                class="text-sm text-base-primary cursor-pointer select-none"
            >
                {{ label }}
            </label>
            <FormTooltip v-if="description" :content="description" position="top">
                <button
                    type="button"
                    class="inline-flex items-center justify-center h-4 w-4
                           rounded-full bg-neutral-200 dark:bg-neutral-700
                           text-base-muted hover:text-base-secondary
                           transition-colors focus:outline-none focus:ring-2
                           focus:ring-primary-500 focus:ring-offset-1"
                    aria-label="Más información"
                >
                    <svg
                        class="h-3 w-3"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        stroke-width="2"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"
                        />
                    </svg>
                </button>
            </FormTooltip>
        </div>
    </div>
</template>
