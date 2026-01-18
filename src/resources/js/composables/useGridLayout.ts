import { computed, toValue, type ComputedRef, type MaybeRefOrGetter } from 'vue';

export type GridColumns = 1 | 2 | 3 | 4;

interface UseGridLayoutReturn {
    gridClasses: ComputedRef<string>;
}

const COLUMN_CLASSES: Record<GridColumns, string> = {
    1: 'grid-cols-1',
    2: 'grid-cols-1 md:grid-cols-2',
    3: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3',
    4: 'grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4',
};

export function useGridLayout(columns: MaybeRefOrGetter<GridColumns>): UseGridLayoutReturn {
    const gridClasses = computed((): string => {
        return COLUMN_CLASSES[toValue(columns)];
    });

    return {
        gridClasses,
    };
}
