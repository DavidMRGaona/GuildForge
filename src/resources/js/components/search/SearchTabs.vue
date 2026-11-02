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
    /** Id of the panel the tabs control. */
    controls: string;
}

const props = defineProps<Props>();

const { t } = useI18n();
const routes = useRoutes();

function tabId(tab: SearchTab): string {
    return `search-tab-${tab.key ?? 'all'}`;
}

/** A tab always opens its first page: `pagina` never travels in the address bar. */
function tabHref(tab: SearchTab): string {
    const params = new URLSearchParams({ q: props.query });
    if (tab.key !== null) {
        params.set('tipo', tab.key);
    }

    return `${routes.search}?${params.toString()}`;
}

/*
 * Tabs keep the page mounted (`preserve-state`), so the results count is announced as it changes.
 * Arrow keys, Home and End move the focus; Enter follows the link (manual activation).
 */
function onKeydown(event: KeyboardEvent): void {
    const list = event.currentTarget as HTMLElement;
    const items = Array.from(list.querySelectorAll<HTMLElement>('[role="tab"]'));
    const current = items.indexOf(event.target as HTMLElement);
    if (current === -1) return;

    const last = items.length - 1;
    const next: Record<string, number> = {
        ArrowRight: current === last ? 0 : current + 1,
        ArrowLeft: current === 0 ? last : current - 1,
        Home: 0,
        End: last,
    };
    const target = next[event.key];
    if (target === undefined) return;

    event.preventDefault();
    items[target]?.focus();
}
</script>

<template>
    <div
        role="tablist"
        :aria-label="t('search.tabsLabel')"
        class="-mx-4 flex gap-1 overflow-x-auto border-b border-default px-4 sm:mx-0 sm:px-0"
        @keydown="onKeydown"
    >
        <Link
            v-for="tab in tabs"
            :id="tabId(tab)"
            :key="tab.key ?? 'all'"
            :href="tabHref(tab)"
            preserve-state
            role="tab"
            :aria-selected="tab.key === active ? 'true' : 'false'"
            :aria-current="tab.key === active ? 'page' : undefined"
            :aria-controls="controls"
            :tabindex="tab.key === active ? 0 : -1"
            class="-mb-px inline-flex min-h-[44px] shrink-0 items-center gap-2 whitespace-nowrap border-b-2 px-3 py-2 text-sm font-medium transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-primary-500"
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
    </div>
</template>
