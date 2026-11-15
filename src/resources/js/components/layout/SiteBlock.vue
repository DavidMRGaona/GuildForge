<script setup lang="ts">
import { computed, onErrorCaptured, provide, ref, watch } from 'vue';
import {
    DEFAULT_SITE_BLOCK_PLACEMENT,
    SITE_BLOCK_PLACEMENT,
} from '@/composables/useSiteBlockPlacement';
import type { ResolvedSiteBlock, SiteBlockPlacement } from '@/types/siteBlocks';
import { resolveSiteBlockComponent } from '@/utils/siteBlockComponents';

/**
 * Draws one site block resolved on the server (footer column, popup). Like ModuleSlot, a block
 * that fails to load or render is left out and never breaks the page around it.
 *
 * `placement` says where it is drawn (`footer` by default); the block reads it with
 * `useSiteBlockPlacement()` (core#718). It travels as a context, not as a prop, so a block that does
 * not ask for it receives and renders exactly what it did before.
 */
const props = withDefaults(
    defineProps<{
        block: ResolvedSiteBlock;
        placement?: SiteBlockPlacement;
    }>(),
    { placement: DEFAULT_SITE_BLOCK_PLACEMENT }
);

provide(
    SITE_BLOCK_PLACEMENT,
    computed(() => props.placement)
);

const component = computed(() => resolveSiteBlockComponent(props.block));

const error = ref<Error | null>(null);
const isDev = import.meta.env.DEV;

watch(
    () => props.block.key,
    () => {
        error.value = null;
    }
);

onErrorCaptured((captured, _instance, info) => {
    const errorObj = captured instanceof Error ? captured : new Error(String(captured));

    console.error(
        `[SiteBlock] Error in block "${props.block.key}":`,
        errorObj.message,
        `\nLifecycle: ${info}`
    );

    error.value = errorObj;

    // Prevent error from propagating — the page renders without this block
    return false;
});
</script>

<template>
    <template v-if="component">
        <div
            v-if="error && isDev"
            class="my-1 rounded border border-dashed border-error bg-error/5 p-2 text-xs text-error"
        >
            <small>Site block error: {{ error.message }}</small>
        </div>
        <Suspense v-else-if="!error">
            <div :data-site-block="block.key">
                <component :is="component" v-bind="block.props" />
            </div>
            <template #fallback>
                <div class="min-h-8" aria-hidden="true"></div>
            </template>
        </Suspense>
    </template>
</template>
