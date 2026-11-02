<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { useRoutes } from '@/composables/useRoutes';
import { internalLinkVisit } from '@/utils/navigation';

defineProps<{
    html: string;
}>();

const routes = useRoutes();

// Links in the guide, action buttons included, arrive as plain HTML: visit them with Inertia
// instead of reloading the whole document
function onClick(event: MouseEvent): void {
    const visit = internalLinkVisit(event, window.location.href, [routes.admin]);
    if (visit !== null) {
        event.preventDefault();
        router.visit(visit);
    }
}
</script>

<template>
    <!-- The HTML is rendered on the server from Markdown with raw HTML stripped -->
    <!-- eslint-disable vue/no-v-html -->
    <div
        class="prose prose-stone max-w-none dark:prose-invert kb-prose"
        @click="onClick"
        v-html="html"
    ></div>
    <!-- eslint-enable vue/no-v-html -->
</template>
