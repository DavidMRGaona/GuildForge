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
        'bg-primary text-white hover:bg-primary-700 focus:ring-primary-500 disabled:opacity-50',
    secondary:
        'bg-muted text-base-primary hover:bg-neutral-200 dark:hover:bg-neutral-700 focus:ring-neutral-400 disabled:opacity-50',
    danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 disabled:opacity-50',
    ghost: 'bg-transparent text-base-secondary hover:bg-muted hover:text-base-primary focus:ring-neutral-400 disabled:opacity-50',
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
    secondary: 'text-base-secondary',
    danger: 'text-white',
    ghost: 'text-base-secondary',
};

const buttonClasses = computed(() => [
    'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-colors',
    'focus:outline-none focus:ring-2 focus:ring-offset-2',
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
        <LoadingSpinner v-if="loading" :size="spinnerSizes[size]" :color="spinnerColor" />
        <slot />
    </button>
</template>
