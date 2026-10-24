<script setup lang="ts">
import type { LegalPageProps } from '@/types/legal';

defineProps<LegalPageProps>();
</script>

<template>
    <section class="bg-muted py-12 sm:py-16">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold tracking-tight text-heading sm:text-4xl">
                {{ title }}
            </h1>
        </div>
    </section>

    <section class="py-12 sm:py-16">
        <div class="mx-auto max-w-4xl px-4 sm:px-6 lg:px-8">
            <!-- Sanitized server-side via SanitizesHtml trait (HTMLPurifier), see LegalPageService -->
            <!-- eslint-disable-next-line vue/no-v-html -->
            <div class="prose prose-stone dark:prose-invert max-w-none" v-html="content" />
            <p v-if="lastUpdated" class="mt-8 text-sm text-muted">
                {{ $t('legal.last_updated') }}: {{ lastUpdated }}
            </p>
        </div>
    </section>
</template>
