import { onScopeDispose, readonly, ref, watch } from 'vue';
import type { DeepReadonly, Ref } from 'vue';
import { useRoutes } from '@/composables/useRoutes';
import type { SearchHit } from '@/types/search';

const DEBOUNCE_MS = 200;
/** Shorter queries get no suggestions; Enter still goes to the results page. */
export const MIN_CHARS = 2;

export interface UseSearchSuggestionsReturn {
    query: Ref<string>;
    results: Readonly<Ref<DeepReadonly<SearchHit[]>>>;
    total: Readonly<Ref<number>>;
    loading: Readonly<Ref<boolean>>;
    /** The last request failed (rate limit, network, server): the results page still works. */
    failed: Readonly<Ref<boolean>>;
    /** Empties the query and the suggestions and cancels whatever is pending. */
    reset: () => void;
}

interface SuggestionsBody {
    results?: unknown;
    total?: unknown;
}

/**
 * Suggestions for the header search panel: waits until typing pauses, asks for at most a
 * handful of hits and drops any answer that a newer query has made stale.
 */
export function useSearchSuggestions(): UseSearchSuggestionsReturn {
    const routes = useRoutes();

    const query = ref('');
    const results = ref<SearchHit[]>([]);
    const total = ref(0);
    const loading = ref(false);
    const failed = ref(false);

    let timer: ReturnType<typeof setTimeout> | null = null;
    let controller: AbortController | null = null;

    function cancelPending(): void {
        if (timer !== null) {
            clearTimeout(timer);
            timer = null;
        }
        controller?.abort();
        controller = null;
        loading.value = false;
    }

    function clearResults(): void {
        results.value = [];
        total.value = 0;
    }

    async function request(term: string): Promise<void> {
        const current = new AbortController();
        controller = current;

        try {
            const response = await fetch(
                `${routes.searchSuggestions}?q=${encodeURIComponent(term)}`,
                { headers: { Accept: 'application/json' }, signal: current.signal }
            );
            if (!response.ok) {
                throw new Error(`Suggestions answered ${response.status}`);
            }
            const body = (await response.json()) as SuggestionsBody;
            if (current.signal.aborted) {
                return;
            }
            results.value = Array.isArray(body.results) ? (body.results as SearchHit[]) : [];
            total.value = typeof body.total === 'number' ? body.total : results.value.length;
            failed.value = false;
        } catch {
            // A newer query aborted this one: its own request decides the state
            if (current.signal.aborted) {
                return;
            }
            clearResults();
            failed.value = true;
        } finally {
            if (controller === current) {
                controller = null;
                loading.value = false;
            }
        }
    }

    watch(query, (value) => {
        cancelPending();

        const term = value.trim();
        if (term.length < MIN_CHARS) {
            clearResults();
            failed.value = false;
            return;
        }

        loading.value = true;
        timer = setTimeout(() => {
            timer = null;
            void request(term);
        }, DEBOUNCE_MS);
    });

    function reset(): void {
        cancelPending();
        query.value = '';
        clearResults();
        failed.value = false;
    }

    onScopeDispose(cancelPending);

    return {
        query,
        results: readonly(results),
        total: readonly(total),
        loading: readonly(loading),
        failed: readonly(failed),
        reset,
    };
}
