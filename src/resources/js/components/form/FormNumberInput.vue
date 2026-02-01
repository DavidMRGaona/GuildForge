<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
    defineProps<{
        modelValue: number | null;
        min?: number | undefined;
        max?: number | undefined;
        step?: number;
        disabled?: boolean;
        error?: string | undefined;
        id?: string;
        name?: string;
        required?: boolean;
        placeholder?: string;
    }>(),
    {
        min: undefined,
        max: undefined,
        step: 1,
        disabled: false,
        error: undefined,
        id: '',
        name: '',
        required: false,
        placeholder: '',
    }
);

const emit = defineEmits<{
    'update:modelValue': [value: number | null];
}>();

const inputId = computed(
    () => props.id || `number-input-${Math.random().toString(36).substring(2, 9)}`
);

const canDecrement = computed(() => {
    if (props.disabled) return false;
    if (props.modelValue === null) return false;
    if (props.min !== undefined && props.modelValue <= props.min) return false;
    return true;
});

const canIncrement = computed(() => {
    if (props.disabled) return false;
    if (props.max !== undefined && props.modelValue !== null && props.modelValue >= props.max)
        return false;
    return true;
});

function decrement(): void {
    if (!canDecrement.value) return;
    const currentValue = props.modelValue ?? 0;
    const newValue = currentValue - props.step;
    const clampedValue = props.min !== undefined ? Math.max(props.min, newValue) : newValue;
    emit('update:modelValue', clampedValue);
}

function increment(): void {
    if (!canIncrement.value) return;
    const currentValue = props.modelValue ?? props.min ?? 0;
    const newValue = currentValue + props.step;
    const clampedValue = props.max !== undefined ? Math.min(props.max, newValue) : newValue;
    emit('update:modelValue', clampedValue);
}

function handleInput(event: Event): void {
    const target = event.target as HTMLInputElement;
    const value = target.value === '' ? null : Number(target.value);
    emit('update:modelValue', value);
}
</script>

<template>
    <div class="relative flex items-center">
        <!-- Decrement button -->
        <button
            type="button"
            tabindex="-1"
            :disabled="!canDecrement"
            class="absolute left-0 z-10 flex h-full w-10 items-center justify-center rounded-l-lg border-r transition-colors duration-150 text-base-secondary hover:bg-neutral-100 hover:text-base-primary disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent dark:hover:bg-neutral-800"
            :class="[error ? 'border-error' : 'border-default']"
            @click="decrement"
        >
            <svg
                class="h-4 w-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M20 12H4" />
            </svg>
        </button>

        <!-- Number input -->
        <input
            :id="inputId"
            type="number"
            :name="name"
            :value="modelValue"
            :min="min"
            :max="max"
            :step="step"
            :required="required"
            :disabled="disabled"
            :placeholder="placeholder"
            :aria-invalid="!!error"
            class="block w-full rounded-lg border bg-surface px-12 py-2.5 text-center text-base-primary placeholder-base-muted transition-colors duration-150 focus:outline-none focus:ring-2 disabled:bg-muted disabled:cursor-not-allowed disabled:opacity-50"
            :class="[
                error
                    ? 'border-error focus:ring-error focus:border-error'
                    : 'border-default hover:border-strong focus:ring-primary-500 focus:border-primary-500',
            ]"
            @input="handleInput"
        />

        <!-- Increment button -->
        <button
            type="button"
            tabindex="-1"
            :disabled="!canIncrement"
            class="absolute right-0 z-10 flex h-full w-10 items-center justify-center rounded-r-lg border-l transition-colors duration-150 text-base-secondary hover:bg-neutral-100 hover:text-base-primary disabled:opacity-40 disabled:cursor-not-allowed disabled:hover:bg-transparent dark:hover:bg-neutral-800"
            :class="[error ? 'border-error' : 'border-default']"
            @click="increment"
        >
            <svg
                class="h-4 w-4"
                fill="none"
                viewBox="0 0 24 24"
                stroke="currentColor"
                stroke-width="2"
            >
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4" />
            </svg>
        </button>
    </div>
</template>
