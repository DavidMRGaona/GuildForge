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
    <label class="inline-flex items-start gap-3 cursor-pointer group">
        <!-- Hidden input for accessibility -->
        <input
            :id="id"
            type="checkbox"
            :checked="isChecked()"
            @change="toggle"
            class="peer sr-only"
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
            <svg
                class="h-3 w-3 transition-all duration-150"
                :class="isChecked() ? 'opacity-100 scale-100' : 'opacity-0 scale-0'"
                viewBox="0 0 12 12"
                fill="none"
            >
                <path
                    d="M2 6l3 3 5-6"
                    stroke="white"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                />
            </svg>
        </div>

        <div class="flex items-center gap-1.5 flex-1 min-w-0">
            <span class="text-sm text-base-primary select-none">
                {{ label }}
            </span>
            <FormTooltip v-if="description" :content="description" position="top">
                <button
                    type="button"
                    class="inline-flex items-center justify-center h-4 w-4 rounded-full bg-neutral-200 dark:bg-neutral-700 text-base-muted hover:text-base-secondary transition-colors focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-1"
                    aria-label="Más información"
                    @click.prevent
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
    </label>
</template>
