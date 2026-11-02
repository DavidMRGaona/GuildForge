<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { useRoutes } from '@/composables/useRoutes';
import type { SearchTab } from '@/types/search';

interface Props {
    tabs: SearchTab[];
    /** Key of the tab on show; null is «Todo». */
    active: string | null;
    query: string;
}

const props = defineProps<Props>();

const { t } = useI18n();
const routes = useRoutes();

/** A tab always opens its first page: `pagina` never travels in the address bar. */
function tabHref(tab: SearchTab): string {
    const params = new URLSearchParams({ q: props.query });
    if (tab.key !== null) {
        params.set('tipo', tab.key);
    }

    return `${routes.search}?${params.toString()}`;
}
</script>

<template>
    <!-- Links that navigate, not an ARIA tab widget. They keep the page mounted, so the
         results count is announced as it changes -->
    <nav :aria-label="t('search.tabsLabel')">
        <!-- The base line is an inset shadow and each underline a border inside the scroll box,
             so scrolling sideways never clips the active one -->
        <ul
            class="-mx-4 flex gap-1 overflow-x-auto overflow-y-hidden px-4 shadow-[inset_0_-1px_0_var(--color-border)] sm:mx-0 sm:px-0"
        >
            <li v-for="tab in tabs" :key="tab.key ?? 'all'" class="shrink-0">
                <Link
                    :href="tabHref(tab)"
                    preserve-state
                    preserve-scroll
                    :aria-current="tab.key === active ? 'page' : undefined"
                    class="inline-flex min-h-[44px] items-center gap-2 whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
                    :class="
                        tab.key === active
                            ? 'border-primary text-base-primary'
                            : 'border-transparent text-base-secondary hover:border-strong hover:text-base-primary'
                    "
                >
                    <span>{{ tab.label }}</span>
                    <span
                        aria-hidden="true"
                        class="rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="
                            tab.key === active
                                ? 'bg-primary-light text-base-primary'
                                : 'bg-muted text-base-secondary'
                        "
                        >{{ tab.count }}</span
                    >
                    <span class="sr-only">{{
                        t('search.resultsCount', { count: tab.count }, tab.count)
                    }}</span>
                </Link>
            </li>
        </ul>
    </nav>
</template>
