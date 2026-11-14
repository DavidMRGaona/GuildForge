<script setup lang="ts">
/**
 * Footer block `core.text`: a rich text written in the panel (core#697).
 */
defineProps<{
    html: string;
}>();

// Not `prose`: the site's prose follows the page's colour scheme, and the footer is dark in both
const textClasses = [
    'space-y-3 text-sm text-neutral-300 dark:text-neutral-400 break-words',
    '[&_strong]:font-semibold [&_strong]:text-white',
    '[&_a]:text-neutral-100 [&_a]:underline [&_a]:underline-offset-4 [&_a:hover]:text-primary',
    '[&_ul]:list-disc [&_ul]:ps-5 [&_ol]:list-decimal [&_ol]:ps-5 [&_li]:mt-1',
    '[&_img]:h-auto [&_img]:max-w-full [&_img]:rounded',
];
</script>

<template>
    <!--
        SECURITY: v-html renders rich text from the footer settings, sanitized on the server
        (RichTextSanitizerInterface, only the footer's own editor images kept).
        @see App\Filament\SiteBlocks\TextFooterBlock
    -->
    <!-- eslint-disable vue/no-v-html -->
    <div v-if="html" :class="textClasses" v-html="html"></div>
    <!-- eslint-enable vue/no-v-html -->
</template>
