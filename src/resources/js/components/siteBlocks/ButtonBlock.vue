<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { buttonSizeClasses } from '@/utils/buttonClasses';
import { isInertiaPage } from '@/utils/inertiaLinks';

/**
 * Footer block `core.button`: a link drawn as a button (core#698). The server only sends an `https`
 * address or a path of the site, so the address is never anything else here.
 */
const props = defineProps<{
    label: string;
    url: string;
    // Not `style`: Vue reads a prop of that name as the element's inline style
    variant: 'primary' | 'secondary';
}>();

// The footer is dark in both colour schemes, so the secondary style never follows the page's one
const classes = computed(() => [
    'inline-flex items-center justify-center rounded-md font-semibold transition-colors',
    // Same size as `BaseButton` `sm`, the one module blocks use in the footer
    buttonSizeClasses.sm,
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
    props.variant === 'secondary'
        ? 'bg-transparent text-white inset-ring inset-ring-neutral-500 hover:bg-white/10'
        : 'bg-(--button-primary-bg) text-(--button-primary-text) inset-ring inset-ring-(--button-primary-border) hover:bg-(--button-primary-hover-bg) hover:text-(--button-primary-hover-text)',
]);
</script>

<template>
    <Link v-if="isInertiaPage(url)" :href="url" :class="classes">{{ label }}</Link>
    <a v-else :href="url" :class="classes">{{ label }}</a>
</template>
