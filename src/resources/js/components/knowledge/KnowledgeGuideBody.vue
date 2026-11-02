<script setup lang="ts">
defineProps<{
    html: string;
}>();
</script>

<template>
    <!-- The HTML is rendered on the server from Markdown with raw HTML stripped -->
    <!-- eslint-disable-next-line vue/no-v-html -->
    <div class="prose prose-stone max-w-none dark:prose-invert kb-prose" v-html="html"></div>
</template>
