<script setup lang="ts">
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

const currentYear = computed(() => new Date().getFullYear());

interface FooterLink {
    key: string;
    href: string;
}

const footerLinks: FooterLink[] = [
    { key: 'common.events', href: '/eventos' },
    { key: 'common.articles', href: '/articulos' },
    { key: 'common.gallery', href: '/galeria' },
    { key: 'common.about', href: '/nosotros' },
];
</script>

<template>
    <footer class="bg-stone-800 dark:bg-stone-950">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="md:flex md:items-center md:justify-between">
                <!-- Footer links -->
                <nav class="flex flex-wrap justify-center gap-x-6 gap-y-2 md:justify-start">
                    <Link
                        v-for="link in footerLinks"
                        :key="link.href"
                        :href="link.href"
                        class="text-sm text-stone-300 transition-colors hover:text-white dark:text-stone-400 dark:hover:text-stone-200"
                    >
                        {{ t(link.key) }}
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
