<script setup lang="ts">
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import SocialLinks from '@/components/ui/SocialLinks.vue';
import SiteBlock from '@/components/layout/SiteBlock.vue';
import type { MenuItem, Navigation } from '@/types/navigation';
import type { SocialMediaLinks } from '@/types/models';
import type { ConfiguredFooter } from '@/types/footer';

const { t } = useI18n();
const page = usePage();

const currentYear = computed(() => new Date().getFullYear());
const appName = computed(() => page.props.appName as string);

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
        key: null,
    },
    {
        id: 'articles',
        label: t('common.articles'),
        href: '/articulos',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
        key: null,
    },
    {
        id: 'gallery',
        label: t('common.gallery'),
        href: '/galeria',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
        key: null,
    },
    {
        id: 'about',
        label: t('common.about'),
        href: '/nosotros',
        target: '_self',
        icon: null,
        children: [],
        isActive: true,
        key: null,
    },
];

const navigation = computed(() => page.props.navigation as Navigation | undefined);
const footerLinks = computed(() => {
    const items = navigation.value?.footer;
    return items && items.length > 0 ? items : fallbackLinks;
});

const socialLinks = computed(() => page.props.socialLinks as SocialMediaLinks | undefined);

// The configurable footer (core#697); without it the fixed footer below is drawn, unchanged
const configured = computed(
    () => (page.props.footer as ConfiguredFooter | null | undefined) ?? null
);

// Literal classes, so Tailwind compiles them: one column on phones, two on tablets, the configured ones from lg
const desktopColumns: Record<number, string> = {
    1: 'lg:grid-cols-1',
    2: 'lg:grid-cols-2',
    3: 'lg:grid-cols-3',
    4: 'lg:grid-cols-4',
};

function columnClasses(footer: ConfiguredFooter): string[] {
    const count = footer.columns.length;

    return [
        'grid grid-cols-1 gap-x-8 gap-y-10',
        count > 1 ? 'sm:grid-cols-2' : '',
        desktopColumns[Math.min(count, 4)] ?? '',
    ];
}
const hasSocialLinks = computed(() => {
    if (!socialLinks.value) return false;
    return Object.values(socialLinks.value).some((url) => url);
});
</script>

<template>
    <footer v-if="configured" class="bg-neutral-800 dark:bg-neutral-900">
        <div class="mx-auto max-w-7xl px-4 py-10 sm:px-6 lg:px-8">
            <div
                v-if="configured.columns.length > 0"
                data-footer-columns
                :class="columnClasses(configured)"
            >
                <div
                    v-for="(column, columnIndex) in configured.columns"
                    :key="columnIndex"
                    data-footer-column
                    class="flex min-w-0 flex-col gap-8"
                >
                    <section
                        v-for="(entry, blockIndex) in column.blocks"
                        :key="`${entry.block.key}-${blockIndex}`"
                        class="min-w-0"
                    >
                        <h2 v-if="entry.title" class="mb-3 text-base font-semibold text-white">
                            {{ entry.title }}
                        </h2>
                        <SiteBlock :block="entry.block" />
                    </section>
                </div>
            </div>

            <!-- Fixed bottom bar: copyright and the published legal pages -->
            <div
                data-footer-bottom
                :class="[
                    'flex flex-col items-center gap-3 text-center md:flex-row md:justify-between md:text-left',
                    configured.columns.length > 0
                        ? 'mt-10 border-t border-neutral-700 pt-6 dark:border-neutral-800'
                        : '',
                ]"
            >
                <p class="text-sm text-neutral-300 dark:text-neutral-400">
                    {{ t('layout.copyright', { year: currentYear, appName: configured.siteName }) }}
                </p>
                <nav
                    v-if="configured.legalLinks.length > 0"
                    :aria-label="t('layout.legalLinks')"
                    class="flex flex-wrap justify-center gap-x-6 gap-y-2 md:justify-end"
                >
                    <Link
                        v-for="link in configured.legalLinks"
                        :key="link.href"
                        :href="link.href"
                        class="text-sm text-neutral-300 transition-colors hover:text-primary dark:text-neutral-400 dark:hover:text-primary"
                    >
                        {{ link.label }}
                    </Link>
                </nav>
            </div>
        </div>
    </footer>
    <footer v-else class="bg-neutral-800 dark:bg-neutral-900">
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="md:flex md:items-center md:justify-between">
                <!-- Footer links -->
                <nav class="flex flex-wrap justify-center gap-x-6 gap-y-2 md:justify-start">
                    <Link
                        v-for="link in footerLinks"
                        :key="link.id"
                        :href="link.href"
                        :target="link.target"
                        class="text-sm text-neutral-300 transition-colors hover:text-primary dark:text-neutral-400 dark:hover:text-primary"
                    >
                        {{ link.label }}
                    </Link>
                </nav>

                <!-- Copyright & Social -->
                <div class="mt-6 text-center md:mt-0 md:text-right">
                    <p class="text-sm text-neutral-300 dark:text-neutral-400">
                        {{ t('layout.copyright', { year: currentYear, appName }) }}
                    </p>
                    <p class="mt-1 text-xs text-neutral-400">
                        {{ t('layout.madeWith') }}
                    </p>
                    <div
                        v-if="hasSocialLinks && socialLinks"
                        class="mt-3 flex justify-center md:justify-end"
                    >
                        <SocialLinks
                            :links="socialLinks"
                            size="sm"
                            variant="dark"
                            balanced
                            class="md:ml-auto md:mr-0"
                        />
                    </div>
                </div>
            </div>
        </div>
    </footer>
</template>
