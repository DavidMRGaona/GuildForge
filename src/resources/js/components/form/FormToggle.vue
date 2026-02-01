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

const toggleId = computed(() => props.id || `toggle-${Math.random().toString(36).substring(2, 9)}`);

function toggle(): void {
    if (!props.disabled) {
        emit('update:modelValue', !props.modelValue);
    }
}
</script>

<template>
    <label
        :for="toggleId"
        class="inline-flex items-center gap-3 cursor-pointer"
        :class="{ 'opacity-50 cursor-not-allowed': disabled }"
    >
        <!-- Hidden checkbox for accessibility -->
        <div class="relative">
            <input
                :id="toggleId"
                type="checkbox"
                :name="name"
                :checked="modelValue"
                :disabled="disabled"
                class="peer sr-only"
                @change="toggle"
            />
            <!-- Toggle track -->
            <div
                class="h-6 w-11 rounded-full border transition-colors duration-200 bg-neutral-200 border-neutral-300 peer-checked:bg-primary-600 peer-checked:border-primary-600 peer-focus:ring-2 peer-focus:ring-primary-500 peer-focus:ring-offset-2 dark:bg-neutral-700 dark:border-neutral-600 dark:peer-checked:bg-primary-500 dark:peer-checked:border-primary-500 dark:peer-focus:ring-offset-neutral-900"
            />
            <!-- Toggle circle -->
            <div
                class="absolute left-0.5 top-0.5 h-5 w-5 rounded-full bg-white shadow-sm transition-transform duration-200 ease-in-out peer-checked:translate-x-5"
            />
        </div>
        <!-- Label text -->
        <span v-if="label" class="text-sm text-base-secondary select-none">
            {{ label }}
        </span>
    </label>
</template>
