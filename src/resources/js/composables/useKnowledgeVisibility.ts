import { ref, type Ref } from 'vue';
import { useAuth } from '@/composables/useAuth';
import { useRoutes } from '@/composables/useRoutes';

interface KnowledgeVisibility {
    isVisible: (id: string) => boolean;
    ready: Ref<boolean>;
}

// One request per page load, shared by every link; the ids live at module level
let pending: Promise<ReadonlySet<string>> | null = null;
const visibleIds = ref<ReadonlySet<string>>(new Set());
const loaded = ref(false);

async function load(url: string): Promise<ReadonlySet<string>> {
    try {
        const response = await fetch(url, {
            headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!response.ok) {
            return new Set();
        }
        const data = (await response.json()) as { ids?: unknown };

        return new Set(
            Array.isArray(data.ids)
                ? data.ids.filter((id): id is string => typeof id === 'string')
                : []
        );
    } catch {
        // A help link is never worth an error: no ids means no links
        return new Set();
    }
}

/** Forgets the memoized request. Meant for tests. */
export function resetKnowledgeVisibility(): void {
    pending = null;
    visibleIds.value = new Set();
    loaded.value = false;
}

export function useKnowledgeVisibility(): KnowledgeVisibility {
    const { isAuthenticated } = useAuth();
    const routes = useRoutes();

    if (isAuthenticated.value && pending === null) {
        pending = load(routes.knowledge.visible).then((ids) => {
            visibleIds.value = ids;
            loaded.value = true;

            return ids;
        });
    }

    return {
        isVisible: (id: string): boolean => isAuthenticated.value && visibleIds.value.has(id),
        ready: loaded,
    };
}
