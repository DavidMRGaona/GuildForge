<script setup lang="ts">
import { computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage } from '@inertiajs/vue3';
import { useAppStore } from '@/stores/useAppStore';
import { useFavicons } from '@/composables/useFavicons';
import type { ThemeSettings } from '@/types/inertia';
import TheHeader from '@/components/layout/TheHeader.vue';
import TheFooter from '@/components/layout/TheFooter.vue';
import NotificationToast from '@/components/ui/NotificationToast.vue';
import ModuleSlot from '@/components/layout/ModuleSlot.vue';

const { t } = useI18n();
const page = usePage();
const appStore = useAppStore();

// Initialize dynamic favicon switching based on theme
useFavicons();

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
        class="flex min-h-screen flex-col bg-page transition-colors duration-200"
    >
        <!-- Inject CSS Variables -->
        <component :is="'style'" v-if="cssVariables">{{ cssVariables }}</component>

        <!-- Load Google Fonts -->
        <Teleport to="head">
            <link v-if="googleFontsUrl" rel="stylesheet" :href="googleFontsUrl" />
        </Teleport>

        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:absolute focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-surface focus:px-4 focus:py-2 focus:text-primary focus:ring-2 focus:ring-[var(--color-primary)]"
        >
            {{ t('a11y.skipToContent') }}
        </a>
        <ModuleSlot name="before-header" />
        <TheHeader />
        <ModuleSlot name="after-header" />
        <main id="main-content" class="flex-1">
            <ModuleSlot name="before-content" />
            <slot />
            <ModuleSlot name="after-content" />
        </main>
        <ModuleSlot name="before-footer" />
        <TheFooter />
        <ModuleSlot name="after-footer" />
        <NotificationToast />
    </div>
</template>
