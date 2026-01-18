<script setup lang="ts">
import { useI18n } from 'vue-i18n';
import TheHeader from '@/components/layout/TheHeader.vue';
import TheFooter from '@/components/layout/TheFooter.vue';

const { t } = useI18n();
</script>

<template>
    <div class="flex min-h-screen flex-col bg-gray-50">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:text-amber-600 focus:ring-2 focus:ring-amber-500"
        >
            {{ t('a11y.skipToContent') }}
        </a>
        <TheHeader />
        <main id="main-content" class="flex-1">
            <slot />
        </main>
        <TheFooter />
    </div>
</template>
