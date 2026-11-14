<script setup lang="ts">
import { computed, onErrorCaptured, ref, watch } from 'vue';
import type { ResolvedSiteBlock } from '@/types/siteBlocks';
import { resolveSiteBlockComponent } from '@/utils/siteBlockComponents';

/**
 * Draws one site block resolved on the server (footer column, popup). Like ModuleSlot, a block
 * that fails to load or render is left out and never breaks the page around it.
 */
const props = defineProps<{
    block: ResolvedSiteBlock;
}>();

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
