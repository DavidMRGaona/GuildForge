<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';
import { Search } from 'lucide-vue-next';

interface Props {
    initialQuery?: string;
    autoFocus?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    initialQuery: '',
    autoFocus: false,
});

const { t } = useI18n();
const query = ref(props.initialQuery);
const inputRef = ref<HTMLInputElement | null>(null);
let debounceTimeout: ReturnType<typeof setTimeout> | null = null;

function debounce(fn: () => void, delay: number): void {
    if (debounceTimeout) {
        clearTimeout(debounceTimeout);
    }
    debounceTimeout = setTimeout(fn, delay);
}

function performSearch(searchQuery: string): void {
    if (searchQuery.length >= 2) {
        router.get('/buscar', { q: searchQuery }, { preserveState: true });
    }
}

function handleSubmit(): void {
    if (query.value.length >= 2) {
        router.get('/buscar', { q: query.value });
    }
}

watch(query, (newQuery) => {
    debounce(() => performSearch(newQuery), 300);
});

onMounted(() => {
    if (props.autoFocus && inputRef.value) {
        inputRef.value.focus();
    }
});
</script>

<template>
    <form @submit.prevent="handleSubmit" class="relative">
        <div class="relative">
            <input
                ref="inputRef"
                v-model="query"
                type="search"
                :placeholder="t('search.placeholder')"
                :aria-label="t('search.placeholder')"
                class="w-full rounded-lg border border-neutral-300 py-2 pl-10 pr-4 focus:border-primary-500 focus:ring-2 focus:ring-primary-500 dark:border-neutral-600 dark:bg-surface dark:text-white"
            />
            <Search
                class="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-neutral-400 dark:text-neutral-500"
                aria-hidden="true"
            />
        </div>
        <p
            v-if="query.length > 0 && query.length < 2"
            class="mt-2 text-sm text-base-muted"
            role="status"
        >
            {{ t('search.minCharsWarning') }}
        </p>
    </form>
</template>
