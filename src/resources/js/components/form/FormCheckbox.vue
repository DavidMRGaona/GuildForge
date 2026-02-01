<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: boolean;
        label?: string;
        disabled?: boolean;
        id?: string;
        name?: string;
    }>(),
    {
        label: '',
        disabled: false,
        id: '',
        name: '',
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
}>();

const checkboxId = computed(
    () => props.id || `checkbox-${Math.random().toString(36).substring(2, 9)}`
);

function toggle(): void {
    if (!props.disabled) {
        emit('update:modelValue', !props.modelValue);
    }
}
</script>

<template>
    <label
        :for="checkboxId"
        class="inline-flex items-start gap-3 cursor-pointer group"
        :class="{ 'opacity-50 cursor-not-allowed': disabled }"
    >
        <!-- Hidden input for accessibility -->
        <input
            :id="checkboxId"
            type="checkbox"
            :name="name"
            :checked="modelValue"
            :disabled="disabled"
            class="peer sr-only"
            @change="toggle"
        />

        <!-- Custom checkbox visual -->
        <div
            class="mt-0.5 h-5 w-5 shrink-0 rounded-md border-2 flex items-center justify-center transition-all duration-150 peer-focus:ring-2 peer-focus:ring-primary-500 peer-focus:ring-offset-2 dark:peer-focus:ring-offset-neutral-900"
            :class="[
                modelValue
                    ? 'bg-primary-600 border-primary-600 dark:bg-primary-500 dark:border-primary-500'
                    : 'bg-surface border-neutral-300 dark:border-neutral-600',
                !disabled && !modelValue && 'group-hover:border-primary-400 dark:group-hover:border-primary-500',
                !disabled && modelValue && 'group-hover:bg-primary-700 dark:group-hover:bg-primary-400',
            ]"
        >
            <!-- Checkmark with animation -->
            <svg
                class="h-3 w-3 transition-all duration-150"
                :class="modelValue ? 'opacity-100 scale-100' : 'opacity-0 scale-0'"
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

        <!-- Label -->
        <span v-if="label || $slots.default" class="text-sm text-base-secondary select-none">
            <slot>{{ label }}</slot>
        </span>
    </label>
</template>
