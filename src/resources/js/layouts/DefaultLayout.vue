<script setup lang="ts">
import { computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import { useAppStore } from '@/stores/useAppStore';
import type { ThemeSettings } from '@/types/inertia';
import TheHeader from '@/components/layout/TheHeader.vue';
import TheFooter from '@/components/layout/TheFooter.vue';
import NotificationToast from '@/components/ui/NotificationToast.vue';

const { t } = useI18n();
const page = usePage();
const appStore = useAppStore();

const theme = computed(() => page.props.theme as ThemeSettings | undefined);
const cssVariables = computed(() => theme.value?.cssVariables ?? '');
const fontHeading = computed(() => theme.value?.fontHeading ?? 'Inter');
const fontBody = computed(() => theme.value?.fontBody ?? 'Inter');

// Generate Google Fonts URL
const googleFontsUrl = computed(() => {
    const fonts = new Set<string>();
    if (fontHeading.value !== 'system-ui') fonts.add(fontHeading.value);
    if (fontBody.value !== 'system-ui') fonts.add(fontBody.value);

    if (fonts.size === 0) return null;

    const fontString = Array.from(fonts)
        .map((f) => `family=${encodeURIComponent(f)}:wght@400;500;600;700`)
        .join('&');

    return `https://fonts.googleapis.com/css2?${fontString}&display=swap`;
});

// Initialize theme settings immediately (synchronously) so components can read them
// This ensures isThemeToggleVisible has the correct value on first render
appStore.setThemeSettings(theme.value);

onMounted(() => {
    appStore.initTheme(theme.value);
});

// Watch for theme changes (e.g., after admin update)
watch(theme, (newTheme) => {
    if (newTheme) {
        appStore.setThemeSettings(newTheme);
        appStore.initTheme(newTheme);
    }
});
</script>

<template>
    <div
        class="flex min-h-screen flex-col bg-stone-50 dark:bg-stone-900 transition-colors duration-200"
    >
        <!-- Inject CSS Variables -->
        <component :is="'style'" v-if="cssVariables">{{ cssVariables }}</component>

        <!-- Load Google Fonts -->
        <Teleport to="head">
            <link v-if="googleFontsUrl" rel="stylesheet" :href="googleFontsUrl" />
        </Teleport>

        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-white focus:px-4 focus:py-2 focus:text-amber-600 focus:ring-2 focus:ring-amber-500 dark:focus:bg-stone-800 dark:focus:text-amber-400"
        >
            {{ t('a11y.skipToContent') }}
        </a>
        <TheHeader />
        <main id="main-content" class="flex-1">
            <slot />
        </main>
        <TheFooter />
        <NotificationToast />
    </div>
</template>
