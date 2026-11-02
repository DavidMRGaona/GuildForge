<script setup lang="ts">
import { computed, onBeforeUnmount, ref, useId } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Search } from '@lucide/vue';
import { useRoutes } from '@/composables/useRoutes';
import KnowledgeSnippet from './KnowledgeSnippet.vue';
import type { KnowledgeSearchHit } from '@/types/knowledge';

const DEBOUNCE_MS = 250;
const MIN_CHARS = 2;

const props = withDefaults(
    defineProps<{
        initialQuery?: string;
        large?: boolean;
    }>(),
    { initialQuery: '', large: false }
);

const { t } = useI18n();
const routes = useRoutes();
const uid = useId();
const listboxId = `${uid}-listbox`;

const query = ref(props.initialQuery);
const results = ref<KnowledgeSearchHit[]>([]);
const activeIndex = ref(-1);
const dismissed = ref(false);

let timer: ReturnType<typeof setTimeout> | null = null;
let controller: AbortController | null = null;

const isOpen = computed(() => !dismissed.value && results.value.length > 0);
const activeId = computed(() =>
    activeIndex.value >= 0 ? `${uid}-option-${activeIndex.value}` : undefined
);

function cancelPending(): void {
    if (timer !== null) {
        clearTimeout(timer);
        timer = null;
    }
    controller?.abort();
    controller = null;
}

async function search(term: string): Promise<void> {
    const current = new AbortController();
    controller = current;

    try {
        const response = await fetch(`${routes.knowledge.search}?q=${encodeURIComponent(term)}`, {
            headers: { Accept: 'application/json' },
            signal: current.signal,
        });
        if (!response.ok) {
            results.value = [];
            return;
        }
        const body = (await response.json()) as { results?: KnowledgeSearchHit[] };
        if (current.signal.aborted) {
            return;
        }
        results.value = Array.isArray(body.results) ? body.results : [];
        activeIndex.value = -1;
    } catch {
        // A failed or aborted search just shows no suggestions
        if (!current.signal.aborted) {
            results.value = [];
        }
    }
}

function onInput(): void {
    cancelPending();
    dismissed.value = false;
    activeIndex.value = -1;

    const term = query.value.trim();
    if (term.length < MIN_CHARS) {
        results.value = [];
        return;
    }
    timer = setTimeout(() => {
        timer = null;
        void search(term);
    }, DEBOUNCE_MS);
}

function openGuide(hit: KnowledgeSearchHit): void {
    router.get(routes.knowledge.show(hit.id));
}

function submit(): void {
    const term = query.value.trim();
    cancelPending();
    if (term === '') {
        return;
    }
    router.get(routes.knowledge.index, { q: term });
}

function onKeydown(event: KeyboardEvent): void {
    const count = results.value.length;

    switch (event.key) {
        case 'ArrowDown':
            if (count > 0) {
                event.preventDefault();
                dismissed.value = false;
                activeIndex.value = (activeIndex.value + 1) % count;
            }
            break;
        case 'ArrowUp':
            if (count > 0) {
                event.preventDefault();
                dismissed.value = false;
                activeIndex.value = activeIndex.value <= 0 ? count - 1 : activeIndex.value - 1;
            }
            break;
        case 'Escape':
            dismissed.value = true;
            activeIndex.value = -1;
            break;
        case 'Enter': {
            event.preventDefault();
            const hit = isOpen.value ? results.value[activeIndex.value] : undefined;
            if (hit) {
                openGuide(hit);
            } else {
                submit();
            }
            break;
        }
    }
}

function onBlur(): void {
    dismissed.value = true;
}

onBeforeUnmount(cancelPending);
</script>

<template>
    <form role="search" class="relative" @submit.prevent="submit">
        <label :for="`${uid}-input`" class="sr-only">{{ t('knowledge.searchLabel') }}</label>
        <div class="relative">
            <input
                :id="`${uid}-input`"
                v-model="query"
                type="text"
                role="combobox"
                autocomplete="off"
                aria-autocomplete="list"
                :aria-expanded="isOpen"
                :aria-controls="listboxId"
                :aria-activedescendant="activeId"
                :placeholder="t('knowledge.searchPlaceholder')"
                class="w-full rounded-lg border border-neutral-300 bg-surface pl-10 pr-4 text-base-primary placeholder:text-base-muted focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 dark:border-neutral-600"
                :class="large ? 'py-3.5 text-base' : 'py-2 text-sm'"
                @input="onInput"
                @keydown="onKeydown"
                @focus="dismissed = false"
                @blur="onBlur"
            />
            <Search
                class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-base-muted"
                aria-hidden="true"
            />
        </div>

        <ul
            v-show="isOpen"
            :id="listboxId"
            role="listbox"
            :aria-label="t('knowledge.searchResultsLabel')"
            class="absolute left-0 right-0 top-full z-30 mt-1 max-h-96 overflow-auto rounded-lg border border-default bg-elevated shadow-lg"
        >
            <li
                v-for="(hit, index) in results"
                :id="`${uid}-option-${index}`"
                :key="hit.id"
                role="option"
                :aria-selected="index === activeIndex"
                class="cursor-pointer px-4 py-2 text-left"
                :class="index === activeIndex ? 'bg-muted' : 'hover:bg-muted'"
                @mousedown.prevent="openGuide(hit)"
            >
                <span class="block text-sm font-semibold text-base-primary">{{ hit.title }}</span>
                <KnowledgeSnippet
                    :segments="hit.snippet"
                    class="block text-xs text-base-secondary"
                />
            </li>
        </ul>
    </form>
</template>
