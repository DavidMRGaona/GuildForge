<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import type { MenuItem, Navigation } from '@/types/navigation';

const { t } = useI18n();
const page = usePage();

const currentYear = computed(() => new Date().getFullYear());

// Fallback footer links if none configured
const fallbackLinks: MenuItem[] = [
    {
        id: 'events',
        label: t('common.events'),
        href: '/eventos',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
    },
    {
        id: 'articles',
        label: t('common.articles'),
        href: '/articulos',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
    },
    {
        id: 'gallery',
        label: t('common.gallery'),
        href: '/galeria',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
    },
    {
        id: 'about',
        label: t('common.about'),
        href: '/nosotros',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
    },
];

const navigation = computed(() => page.props.navigation as Navigation | undefined);
const footerLinks = computed(() => {
    const items = navigation.value?.footer;
    return items && items.length > 0 ? items : fallbackLinks;
});
</script>

<template>
    <footer class="bg-stone-800 dark:bg-stone-950">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="md:flex md:items-center md:justify-between">
                <!-- Footer links -->
                <nav class="flex flex-wrap justify-center gap-x-6 gap-y-2 md:justify-start">
                    <Link
                        v-for="link in footerLinks"
                        :key="link.id"
                        :href="link.href"
                        :target="link.target"
                        class="text-sm text-stone-300 transition-colors hover:text-primary dark:text-stone-400 dark:hover:text-primary"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <!-- Copyright -->
                <div class="mt-6 text-center md:mt-0 md:text-right">
                    <p class="text-sm text-stone-300 dark:text-stone-400">
                        {{ t('layout.copyright', { year: currentYear }) }}
                    </p>
                    <p class="mt-1 text-xs text-stone-500 dark:text-stone-600">
                        {{ t('layout.madeWith') }}
                    </p>
                </div>
            </div>
        </div>
    </footer>
</template>
