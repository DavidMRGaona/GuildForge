import { computed, toValue, type ComputedRef, type MaybeRefOrGetter } from 'vue';
import { router } from '@inertiajs/vue3';
import type { PaginatedResponse } from '@/types';

interface UsePaginationReturn {
    firstItemNumber: ComputedRef<number>;
    lastItemNumber: ComputedRef<number>;
    hasPagination: ComputedRef<boolean>;
    goToPage: (url: string | null) => void;
    goToPrev: () => void;
    goToNext: () => void;
    canGoPrev: ComputedRef<boolean>;
    canGoNext: ComputedRef<boolean>;
}

export function usePagination<T>(
    paginated: MaybeRefOrGetter<PaginatedResponse<T>>
): UsePaginationReturn {
    function goToPage(url: string | null): void {
        if (url) {
            router.visit(url);
        }
    }

    function goToPrev(): void {
        goToPage(toValue(paginated).links.prev);
    }

    function goToNext(): void {
        goToPage(toValue(paginated).links.next);
    }

    const firstItemNumber = computed((): number => {
        const p = toValue(paginated);
        return (p.meta.currentPage - 1) * p.meta.perPage + 1;
    });

    const lastItemNumber = computed((): number => {
        const p = toValue(paginated);
        return Math.min(
            p.meta.currentPage * p.meta.perPage,
            p.meta.total
        );
    });

    const hasPagination = computed((): boolean => {
        return toValue(paginated).meta.lastPage > 1;
    });

    const canGoPrev = computed((): boolean => {
        return toValue(paginated).links.prev !== null;
    });

    const canGoNext = computed((): boolean => {
        return toValue(paginated).links.next !== null;
    });

    return {
        firstItemNumber,
        lastItemNumber,
        hasPagination,
        goToPage,
        goToPrev,
        goToNext,
        canGoPrev,
        canGoNext,
    };
}
