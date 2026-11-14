<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

/**
 * Footer block `core.button`: a link drawn as a button (core#698). The server only sends an `https`
 * address or a path of the site, so the address is never anything else here.
 */
const props = defineProps<{
    label: string;
    url: string;
    external: boolean;
    // Not `style`: Vue reads a prop of that name as the element's inline style
    variant: 'primary' | 'secondary';
}>();

// The footer is dark in both colour schemes, so the secondary style never follows the page's one
const classes = computed(() => [
    'inline-flex items-center justify-center rounded-md px-4 py-2 text-sm font-semibold transition-colors',
    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-primary',
    props.variant === 'secondary'
        ? 'bg-transparent text-white inset-ring inset-ring-neutral-500 hover:bg-white/10'
        : 'bg-(--button-primary-bg) text-(--button-primary-text) inset-ring inset-ring-(--button-primary-border) hover:bg-(--button-primary-hover-bg) hover:text-(--button-primary-hover-text)',
]);
</script>

<template>
    <a v-if="external" :href="url" :class="classes">{{ label }}</a>
    <Link v-else :href="url" :class="classes">{{ label }}</Link>
</template>
