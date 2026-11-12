<script setup lang="ts">
/**
 * The mark a module puts on a menu link: a dot, and what it means for screen readers (inside
 * the link, so its name reads «Foro, hay 3 temas sin leer»).
 */
defineProps<{
    label: string;
}>();
</script>

<template>
    <span data-testid="menu-badge">
        <span
            class="ms-1.5 inline-block h-2 w-2 rounded-full bg-primary-500 align-middle"
            aria-hidden="true"
        />
        <span class="sr-only">, {{ label }}</span>
    </span>
</template>
