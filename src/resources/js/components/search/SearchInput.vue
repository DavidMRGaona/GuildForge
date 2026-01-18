<script setup lang="ts">
import { ref, watch, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { useI18n } from 'vue-i18n';

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
                class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg
                       focus:ring-2 focus:ring-amber-500 focus:border-amber-500
                       dark:bg-gray-800 dark:border-gray-600 dark:text-white"
            />
            <svg
                class="absolute left-3 top-1/2 -translate-y-1/2 w-5 h-5 text-gray-400"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
                aria-hidden="true"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                />
            </svg>
        </div>
        <p
            v-if="query.length > 0 && query.length < 2"
            class="mt-2 text-sm text-gray-500"
            role="status"
        >
            {{ t('search.minCharsWarning') }}
        </p>
    </form>
</template>
