<script setup lang="ts">
import { computed, onMounted, watch } from 'vue';
import { useI18n } from 'vue-i18n';
import { usePage, Link } from '@inertiajs/vue3';
import { useAppStore } from '@/stores/useAppStore';
import type { ThemeSettings } from '@/types/inertia';

interface Props {
    title: string;
    subtitle?: string;
}

const props = defineProps<Props>();

const { t } = useI18n();
const page = usePage();
const appStore = useAppStore();

const theme = computed(() => page.props.theme as ThemeSettings | undefined);
const cssVariables = computed(() => theme.value?.cssVariables ?? '');
const fontHeading = computed(() => theme.value?.fontHeading ?? 'Inter');
const fontBody = computed(() => theme.value?.fontBody ?? 'Inter');

const googleFontsUrl = computed(() => {
    const fonts = new Set<string>();
    if (fontHeading.value !== 'system-ui') fonts.add(fontHeading.value);
    if (fontBody.value !== 'system-ui') fonts.add(fontBody.value);

    if (fonts.size === 0) return null;

    const fontString = Array.from(fonts)
        .map(f => `family=${encodeURIComponent(f)}:wght@400;500;600;700`)
        .join('&');

    return `https://fonts.googleapis.com/css2?${fontString}&display=swap`;
});

appStore.setThemeSettings(theme.value);

onMounted(() => {
    appStore.initTheme(theme.value);
});

watch(theme, (newTheme) => {
    if (newTheme) {
        appStore.setThemeSettings(newTheme);
        appStore.initTheme(newTheme);
    }
});
</script>

<template>
    <div class="min-h-screen flex flex-col justify-center py-12 sm:px-6 lg:px-8 bg-stone-50 dark:bg-stone-900 transition-colors duration-200">
        <!-- Inject CSS Variables -->
        <component :is="'style'" v-if="cssVariables">{{ cssVariables }}</component>

        <!-- Load Google Fonts -->
        <Teleport to="head">
            <link
                v-if="googleFontsUrl"
                rel="stylesheet"
                :href="googleFontsUrl"
            />
        </Teleport>

        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <Link href="/" class="flex justify-center">
                <span class="text-2xl font-bold text-amber-600 dark:text-amber-400">
                    {{ page.props.appName }}
                </span>
            </Link>
            <h1 class="mt-6 text-center text-3xl font-bold tracking-tight text-stone-900 dark:text-stone-100">
                {{ props.title }}
            </h1>
            <p v-if="props.subtitle" class="mt-2 text-center text-sm text-stone-600 dark:text-stone-400">
                {{ props.subtitle }}
            </p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white dark:bg-stone-800 py-8 px-4 shadow sm:rounded-lg sm:px-10">
                <slot />
            </div>
        </div>

        <div class="mt-8 text-center">
            <Link href="/" class="text-sm text-stone-600 dark:text-stone-400 hover:text-amber-600 dark:hover:text-amber-400">
                ← {{ t('auth.backToHome') }}
            </Link>
        </div>
    </div>
</template>
