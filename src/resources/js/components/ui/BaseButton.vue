<script setup lang="ts">
import { computed } from 'vue';
import LoadingSpinner from './LoadingSpinner.vue';

interface Props {
    variant?: 'primary' | 'secondary' | 'danger' | 'ghost';
    size?: 'sm' | 'md' | 'lg';
    disabled?: boolean;
    loading?: boolean;
    type?: 'button' | 'submit' | 'reset';
}

const props = withDefaults(defineProps<Props>(), {
    variant: 'primary',
    size: 'md',
    disabled: false,
    loading: false,
    type: 'button',
});

const emit = defineEmits<{
    click: [event: Event];
}>();

const variantClasses: Record<NonNullable<typeof props.variant>, string> = {
    primary:
        'bg-amber-600 text-white hover:bg-amber-700 focus:ring-amber-500 disabled:bg-amber-300 dark:bg-amber-500 dark:hover:bg-amber-400 dark:focus:ring-amber-400 dark:disabled:bg-amber-800 dark:disabled:text-amber-950',
    secondary:
        'bg-stone-200 text-stone-900 hover:bg-stone-300 focus:ring-stone-500 disabled:bg-stone-100 disabled:text-stone-400 dark:bg-stone-700 dark:text-stone-100 dark:hover:bg-stone-600 dark:focus:ring-stone-400 dark:disabled:bg-stone-800 dark:disabled:text-stone-500',
    danger:
        'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 disabled:bg-red-300 dark:bg-red-500 dark:hover:bg-red-400 dark:focus:ring-red-400 dark:disabled:bg-red-800',
    ghost:
        'bg-transparent text-stone-700 hover:bg-stone-100 focus:ring-stone-500 disabled:text-stone-400 dark:text-stone-300 dark:hover:bg-stone-800 dark:hover:text-stone-100 dark:focus:ring-stone-400 dark:disabled:text-stone-600',
};

const sizeClasses: Record<NonNullable<typeof props.size>, string> = {
    sm: 'px-4 py-2 text-sm min-h-[44px] min-w-[44px]',
    md: 'px-4 py-2 text-base min-h-[44px]',
    lg: 'px-6 py-3 text-lg min-h-[44px]',
};

const spinnerSizes: Record<NonNullable<typeof props.size>, 'sm' | 'md' | 'lg'> = {
    sm: 'sm',
    md: 'sm',
    lg: 'md',
};

const spinnerColors: Record<NonNullable<typeof props.variant>, string> = {
    primary: 'text-white',
    secondary: 'text-stone-600 dark:text-stone-300',
    danger: 'text-white',
    ghost: 'text-stone-600 dark:text-stone-300',
};

const buttonClasses = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors',
    'focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-stone-900',
    'disabled:cursor-not-allowed',
    variantClasses[props.variant],
    sizeClasses[props.size],
]);

const spinnerColor = computed(() => spinnerColors[props.variant]);

function handleClick(event: Event): void {
    if (!props.disabled && !props.loading) {
        emit('click', event);
    }
}
</script>

<template>
    <button
        :type="type"
        :disabled="disabled || loading"
        :class="buttonClasses"
        @click="handleClick"
    >
        <LoadingSpinner
            v-if="loading"
            :size="spinnerSizes[size]"
            :color="spinnerColor"
        />
        <slot />
    </button>
</template>
