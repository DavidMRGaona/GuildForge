import { computed, unref } from 'vue';
import type { ComputedRef, MaybeRef } from 'vue';
import type { Tag } from '@/types/models';

export interface UseTagsReturn {
    categoryTag: ComputedRef<Tag | undefined>;
    additionalTags: ComputedRef<Tag[]>;
    hasTags: ComputedRef<boolean>;
}

/**
 * Composable for handling tag categorization.
 *
 * Splits tags into:
 * - categoryTag: The first parent tag (parentId === null)
 * - additionalTags: All child tags (parentId !== null)
 *
 * @param tags - Array of tags (can be a ref or plain value)
 */
export function useTags(tags: MaybeRef<Tag[] | undefined>): UseTagsReturn {
    const categoryTag = computed(() =>
        unref(tags)?.find((tag) => tag.parentId === null)
    );

    const additionalTags = computed(() =>
        unref(tags)?.filter((tag) => tag.parentId !== null) ?? []
    );

    const hasTags = computed(() =>
        categoryTag.value !== undefined || additionalTags.value.length > 0
    );

    return {
        categoryTag,
        additionalTags,
        hasTags,
    };
}
