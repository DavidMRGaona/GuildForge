<script setup lang="ts">
/**
 * Footer block `core.text`: a rich text written in the panel (core#697).
 */
defineProps<{
    html: string;
}>();
</script>

<template>
    <!--
        SECURITY: v-html renders rich text from the footer settings, sanitized on the server
        (RichTextSanitizerInterface, only the footer's own editor images kept).
        @see App\Filament\SiteBlocks\TextFooterBlock
    -->
    <!-- eslint-disable vue/no-v-html -->
    <div
        v-if="html"
        class="prose prose-sm prose-invert max-w-none text-neutral-300 prose-a:text-neutral-100 hover:prose-a:text-primary dark:text-neutral-400"
        v-html="html"
    ></div>
    <!-- eslint-enable vue/no-v-html -->
</template>
