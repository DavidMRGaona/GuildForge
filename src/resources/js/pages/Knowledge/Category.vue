<script setup lang="ts">
// Placeholder until the help centre pages are built
defineProps<{ page: { category: { id: string; label: string }; guides: unknown[] } }>();
</script>

<template>
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <h1>{{ $t('knowledge.title') }}</h1>
    </div>
</template>
